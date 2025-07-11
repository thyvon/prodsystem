export const confirmAction = (title, message) => {
  return new Promise((resolve) => {
    initApp.playSound('template/media/sound', 'bigbox') // ✅ Play confirmation sound

    bootbox.confirm({
      title: `<i class='fal fa-times-circle text-danger mr-2'></i> ${title}`,
      message: message,
      centerVertical: true,
      swapButtonOrder: true,
      buttons: {
        confirm: {
          label: 'Yes',
          className: 'btn-danger shadow-0'
        },
        cancel: {
          label: 'No',
          className: 'btn-default'
        }
      },
      className: 'modal-alert',
      closeButton: false,
      callback: (result) => {
        resolve(result)
      }
    })
  })
}

export const showAlert = (title, message, type = 'success') => {
  initApp.playSound('template/media/sound', 'voice_on') // ✅ Play success sound

  const icons = {
    success: 'fa-check-circle text-success',
    danger: 'fa-times-circle text-danger',
    info: 'fa-info-circle text-info'
  }

  const dialog = bootbox.alert({
    title: `<i class='fal ${icons[type] || icons.info} mr-2'></i> <span class='text-${type} fw-500'>${title}</span>`,
    message: `<span>${message}</span>`,
    centerVertical: true,
    className: 'modal-alert',
    closeButton: false
  })

  setTimeout(() => {
    dialog.modal('hide')
  }, 4000) // Auto-close after 2 seconds
}
