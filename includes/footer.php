<?php
$debug = $_ENV['DEBUG'] ?? false;
if (!$debug) {
    echo "<script>console.log = function(){};</script>";
}
?>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/security.js"></script>
</body>
</html>