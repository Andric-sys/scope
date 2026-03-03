<?php
// /partials/footer.php (SOPORTE)
// Footer + Flash + Modal Global + PWA
?>

<footer class="app-footer">
  CORE Global Logistics · Sistema de Gestión<br>
  Versión 1.0 · © <?= date('Y') ?>
</footer>

<?php include __DIR__ . '/flash.php'; ?>

<!-- =========================
     MODAL GLOBAL (ÚNICO)
========================= -->
<div id="cglModal" class="cgl-modal" hidden>
  <div class="cgl-modal-card">
    <div class="cgl-modal-header">
      <div class="cgl-modal-title" id="cglModalTitle">Modal</div>
      <button type="button" id="cglModalClose" class="cgl-modal-close" aria-label="Cerrar">
        ✖
      </button>
    </div>

    <div class="cgl-modal-body" id="cglModalBody"></div>

    <div class="cgl-modal-footer" id="cglModalFooter"></div>
  </div>
</div>

<script src="assets/js/app.js"></script>

<script>
if ('serviceWorker' in navigator) {
  // ✅ robusto: relativo al proyecto actual (evita fallos por rutas)
  navigator.serviceWorker.register('sw.js');
}
</script>

</body>
</html>
