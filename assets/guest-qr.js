document.querySelectorAll('[data-guest-qr]').forEach((target) => {
  try {
    new QRCode(target, { text: target.dataset.url, width: 256, height: 256, correctLevel: QRCode.CorrectLevel.M });
  } catch (_) {
    target.hidden = true; // The invitation URL remains usable if canvas/QR generation is unavailable.
  }
});
