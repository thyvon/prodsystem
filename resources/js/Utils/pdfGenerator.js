import html2pdf from 'html2pdf.js'

export async function generatePdfFromElement(elementId, fileName = 'document.pdf') {
  const element = document.getElementById(elementId)
  if (!element) return null

  const opt = {
    margin: 10,
    filename: fileName,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: {
      scale: 4,                // higher resolution
      useCORS: true,
      letterRendering: true,
      logging: true,           // check for font warnings
      allowTaint: false,
      backgroundColor: null,
    },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  }

  return new Promise((resolve) => {
    html2pdf()
      .set(opt)
      .from(element)
      .toPdf()
      .get('pdf')
      .then((pdf) => {
        const blob = pdf.output('blob')
        const url = URL.createObjectURL(blob)
        resolve(url) // return blob URL
      })
  })
}
