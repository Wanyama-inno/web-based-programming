</main>
<footer>
    <p>📚 LibraryOS &copy; <?= date('Y') ?> — Library Management System. Built with PHP &amp; MySQL.</p>
</footer>

<script>
// Auto-close flash messages
document.querySelectorAll('.flash').forEach(function(el){
    setTimeout(function(){ el.style.opacity='0'; el.style.transition='opacity .5s'; setTimeout(function(){ el.remove(); },500); }, 4000);
});

// Confirm delete actions
document.querySelectorAll('[data-confirm]').forEach(function(el){
    el.addEventListener('click', function(e){
        if(!confirm(this.dataset.confirm)) e.preventDefault();
    });
});

// Dropdown toggle functionality
document.querySelectorAll('.nav-dropdown-toggle').forEach(function(toggle){
    toggle.addEventListener('click', function(e){
        e.preventDefault();
        var dropdown = this.nextElementSibling;
        if (dropdown.classList.contains('open')) {
            dropdown.classList.remove('open');
            this.setAttribute('aria-expanded', 'false');
        } else {
            // Close other open dropdowns
            document.querySelectorAll('.nav-dropdown.open').forEach(function(openDropdown){
                openDropdown.classList.remove('open');
                openDropdown.previousElementSibling.setAttribute('aria-expanded', 'false');
            });
            dropdown.classList.add('open');
            this.setAttribute('aria-expanded', 'true');
        }
    });
});

// Close dropdowns when clicking outside
document.addEventListener('click', function(e){
    if (!e.target.closest('.nav-item')) {
        document.querySelectorAll('.nav-dropdown.open').forEach(function(dropdown){
            dropdown.classList.remove('open');
            dropdown.previousElementSibling.setAttribute('aria-expanded', 'false');
        });
    }
});
</script>
</body>
</html>
