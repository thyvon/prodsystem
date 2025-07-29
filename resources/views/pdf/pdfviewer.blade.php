<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>PDF.js CDN Viewer</title>
    <style>
      #pdf-canvas {
        border: 1px solid #000;
        max-width: 100%;
      }
    </style>
</head>
<body>

  <h1>PDF.js CDN Example</h1>

  <!-- Canvas where PDF page will render -->
  <canvas id="pdf-canvas"></canvas>

  <!-- PDF.js core library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>

  <!-- PDF.js worker -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js"></script>

  <script>
    // Tell PDF.js where to find the worker script
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

    // URL of the PDF file
    const url = '{{ asset("storage/sample.pdf") }}';

    // Load PDF document
    pdfjsLib.getDocument(url).promise.then(pdf => {
      // Get first page
      return pdf.getPage(1);
    }).then(page => {
      // Set scale (zoom level)
      const scale = 1.5;
      const viewport = page.getViewport({ scale });

      // Prepare canvas using PDF page dimensions
      const canvas = document.getElementById('pdf-canvas');
      const context = canvas.getContext('2d');
      canvas.height = viewport.height;
      canvas.width = viewport.width;

      // Render PDF page into canvas context
      const renderContext = {
        canvasContext: context,
        viewport: viewport
      };
      page.render(renderContext);
    }).catch(error => {
      console.error('Error loading PDF:', error);
    });
  </script>
  
</body>
</html>
