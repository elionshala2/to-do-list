<?php
// includes/footer.php
?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <a href="<?= defined('BASE') ? BASE : '' ?>index.php" class="logo">To-Do List</a>
            <p>Organizohu. Fokusohu. Arri me shume.</p>
        </div>

        <div class="footer-links">
            <a href="index.php">Kryefaqja</a>
            <a href="login.php">Login</a>
            <a href="signup.php">Sign Up</a>
        </div>

        <div class="footer-copy">
            &copy; <?= date('Y') ?> To-Do List. Te gjitha te drejtat e rezervuara. 
            Elion Shala
        </div>
    </div>
</footer>