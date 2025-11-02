<?php
$debug = $_ENV['DEBUG'] ?? false;
if (!$debug) {
    echo "<script nonce=\"$nonce\">console.log = function(){};</script>";
}
?>
    <script nonce="<?php echo $nonce; ?>" src="../assets/js/main.js"></script>
    <script nonce="<?php echo $nonce; ?>" src="../assets/js/security.js"></script>
    <script nonce="<?php echo $nonce; ?>" src="../assets/js/face.js"></script>
</body>
</html>