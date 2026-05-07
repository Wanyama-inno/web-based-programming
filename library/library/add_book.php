<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$conn  = getDBConnection();
$editId = intval($_GET['edit'] ?? 0);
$delId  = intval($_GET['delete'] ?? 0);

// Handle Delete
if ($delId > 0) {
    $bookRow = $conn->query("SELECT title FROM books WHERE id = $delId")->fetch(PDO::FETCH_ASSOC);
    $stmt = $conn->prepare("DELETE FROM books WHERE id = :id");
    $ok = $stmt->execute([':id' => $delId]);

    if ($ok) {
        logActivity($_SESSION['user_id'], 'book_deleted',
            "Deleted book ID:{$delId}" . ($bookRow ? " \"{$bookRow['title']}\"" : ''));
        setFlash('success', 'Book deleted successfully.');
    } else {
        setFlash('error', 'Failed to delete book.');
    }

    header('Location: books.php');
    exit();
}

// Fetch book for editing
$editBook = null;
if ($editId > 0) {
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editBook = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $author   = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $isbn     = trim($_POST['isbn'] ?? '');
    $qty      = max(1, intval($_POST['quantity'] ?? 1));
    $desc     = trim($_POST['description'] ?? '');
    $color    = trim($_POST['cover_color'] ?? '#FFFF00');
    $id       = intval($_POST['edit_id'] ?? 0);

    if (empty($title) || empty($author)) {
        setFlash('error', 'Title and Author are required.');
    } else {
        if ($id > 0) {
            $oldStmt = $conn->prepare("SELECT total_copies, available_copies FROM books WHERE id = :id");
            $oldStmt->execute([':id' => $id]);
            $old = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_copies' => 0, 'available_copies' => 0];

            $diff = $qty - (int)$old['total_copies'];
            $newAvail = max(0, (int)$old['available_copies'] + $diff);

            $stmt = $conn->prepare(
                "UPDATE books SET title = :title, author = :author, category = :category, isbn = :isbn, total_copies = :qty, available_copies = :available, description = :desc, cover_color = :cover WHERE id = :id"
            );
            $ok = $stmt->execute([
                ':title'     => $title,
                ':author'    => $author,
                ':category'  => $category,
                ':isbn'      => $isbn,
                ':qty'       => $qty,
                ':available' => $newAvail,
                ':desc'      => $desc,
                ':cover'     => $color,
                ':id'        => $id,
            ]);

            if ($ok) {
                logActivity($_SESSION['user_id'], 'book_edited', "Updated book ID:{$id} \"{$title}\"");
            }
            setFlash($ok ? 'success' : 'error', $ok ? 'Book updated successfully.' : 'Failed to update book.');
            header('Location: books.php');
            exit();
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO books (title, author, category, isbn, total_copies, available_copies, description, cover_color) VALUES (:title, :author, :category, :isbn, :qty, :qty, :desc, :cover)"
            );
            $ok = $stmt->execute([
                ':title'    => $title,
                ':author'   => $author,
                ':category' => $category,
                ':isbn'     => $isbn,
                ':qty'      => $qty,
                ':desc'     => $desc,
                ':cover'    => $color,
            ]);
            $newId = $ok ? $conn->lastInsertId() : 0;

            if ($ok) {
                logActivity($_SESSION['user_id'], 'book_added', "Added \"{$title}\" by {$author} (ID:{$newId})");
            }
            setFlash($ok ? 'success' : 'error', $ok ? 'Book added successfully!' : 'Failed to add book. ISBN may already exist.');
            header('Location: books.php');
            exit();
        }
    }
}

$pageTitle = $editBook ? 'Edit Book' : 'Add Book';
require_once 'includes/header.php';
$b = $editBook;
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="books.php">Books</a> › <?= $b ? 'Edit Book' : 'Add New Book' ?></div>
        <h1><?= $b ? 'Edit Book' : 'Add New Book' ?></h1>
        <p><?= $b ? 'Update book details in the catalog' : 'Add a new book to the library collection' ?></p>
    </div>
</div>

<div style="max-width:680px">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= $b ? '✏️ Edit: ' . sanitize($b['title']) : '📚 Book Information' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if($b): ?>
                    <input type="hidden" name="edit_id" value="<?= $b['id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Book Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. The Great Gatsby" value="<?= sanitize($b['title'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Author *</label>
                        <input type="text" name="author" class="form-control" placeholder="e.g. F. Scott Fitzgerald" value="<?= sanitize($b['author'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" placeholder="e.g. Fiction, Science, History" value="<?= sanitize($b['category'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ISBN</label>
                        <input type="text" name="isbn" class="form-control" placeholder="978-0-000-00000-0" value="<?= sanitize($b['isbn'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Copies</label>
                        <input type="number" name="quantity" class="form-control" min="1" max="999" value="<?= intval($b['total_copies'] ?? 1) ?>" required>
                        <?php if($b): ?>
                            <div class="form-hint">Currently <?= $b['available_copies'] ?> available. Changing quantity adjusts proportionally.</div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" placeholder="Brief description of the book..."><?= sanitize($b['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cover Color</label>
                        <div style="display:flex;gap:10px;align-items:center">
                            <input type="color" name="cover_color" value="<?= sanitize($b['cover_color'] ?? '#FFFF00') ?>" style="width:48px;height:38px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;padding:2px">
                            <span style="font-size:.85rem;color:var(--ink-muted)">Pick a color for the book cover display</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="books.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary"><?= $b ? '💾 Save Changes' : '➕ Add Book' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
