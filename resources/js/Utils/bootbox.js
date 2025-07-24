export const confirmAction = (title, message) => {
  return new Promise((resolve) => {
    try {
      if (initApp?.playSound) {
        initApp.playSound('template/media/sound', 'bigbox'); // ✅ Play confirmation sound
      } else {
        console.warn('initApp.playSound is not available');
      }

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
          resolve(result);
        }
      });
    } catch (error) {
      console.error('Confirm dialog failed:', error);
      resolve(false); // Default to cancel on error
    }
  });
};

export const showAlert = (title, message, type = 'success', duration = 4000) => {
  return new Promise((resolve) => {
    try {
      if (initApp?.playSound) {
        const soundMap = {
          success: 'voice_on',
          danger: 'voice_off',
          info: 'voice_on'
        };
        initApp.playSound('/template/media/sound', soundMap[type] || 'voice_on');
      } else {
        console.warn('initApp.playSound is not available');
      }

      const icons = {
        success: 'fa-check-circle text-success',
        danger: 'fa-times-circle text-danger',
        info: 'fa-info-circle text-info'
      };

      const dialog = bootbox.alert({
        title: `<i class='fal ${icons[type] || icons.info} mr-2'></i> <span class='text-${type} fw-500'>${title}</span>`,
        message: message,
        centerVertical: true,
        className: 'modal-alert',
        closeButton: type === 'danger' ? true : false,
        callback: () => resolve(),
        onEscape: () => resolve()
      });

      if (type !== 'danger') {
        setTimeout(() => {
          dialog.modal('hide');
          resolve();
        }, duration);
      }
    } catch (error) {
      console.error('Alert dialog failed:', error);
      resolve();
    }
  });
};