<?php
// /partials/flash.php (SOPORTE)
if (session_status() === PHP_SESSION_NONE) session_start();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if (!$flash) return;

$type  = $flash['type']  ?? 'info';
$title = $flash['title'] ?? '';
$text  = $flash['text']  ?? '';
$toast = !empty($flash['toast']);
$timer = (int)($flash['timer'] ?? 2200);
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  Swal.fire({
    icon: <?= json_encode($type) ?>,
    title: <?= json_encode($title) ?>,
    text: <?= json_encode($text) ?>,
    toast: <?= $toast ? 'true' : 'false' ?>,
    position: <?= $toast ? json_encode('top-end') : json_encode('center') ?>,
    showConfirmButton: <?= $toast ? 'false' : 'true' ?>,
    timer: <?= $toast ? $timer : 'undefined' ?>,
    timerProgressBar: <?= $toast ? 'true' : 'false' ?>,
  });
});
</script>
