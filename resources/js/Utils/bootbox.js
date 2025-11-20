export const confirmAction = (title = 'Confirm', message = 'Are you sure?', type = 'info') => {
  const iconMap = {
    info: 'fal fa-question-circle text-primary',
    warning: 'fal fa-exclamation-triangle text-warning',
    success: 'fal fa-check-circle text-success',
    danger: 'fal fa-times-circle text-danger'
  };
  const iconClass = iconMap[type] || iconMap.info;

  // Ensure message is not null/empty
  if (!message || message.trim() === '') message = 'Are you sure?';

  return new Promise((resolve) => {
    try {
      // Play sound if available
      if (initApp?.playSound) {
        initApp.playSound('/template/media/sound', 'bigbox');
      } else {
        const audio = new Audio('/template/media/sound/bigbox');
        audio.play().catch((err) => console.warn('Sound playback failed:', err));
      }

      bootbox.confirm({
        title: `<i class='${iconClass} mr-2'></i> ${title}`,
        message,
        centerVertical: true,
        swapButtonOrder: true,
        buttons: {
          confirm: {
            label: 'Yes',
            className: type === 'danger' ? 'btn-danger shadow-0' : 'btn-primary shadow-0'
          },
          cancel: {
            label: 'No',
            className: 'btn-default'
          }
        },
        className: 'modal-alert',
        closeButton: false,
        callback: (result) => resolve(result)
      });
    } catch (error) {
      console.error('Confirm dialog failed:', error);
      resolve(false);
    }
  });
};

export const showAlert = (title = '', message = '', type = 'success', duration = 4000) => {
  const iconMap = {
    success: 'fal fa-check-circle text-success',
    danger: 'fal fa-times-circle text-danger',
    info: 'fal fa-info-circle text-info',
    warning: 'fal fa-exclamation-triangle text-warning'
  };

  return new Promise((resolve) => {
    try {
      // Play sound if available
      if (initApp?.playSound) {
        const soundMap = { success: 'voice_on', danger: 'voice_off', info: 'voice_on', warning: 'voice_on' };
        initApp.playSound('/template/media/sound', soundMap[type] || 'voice_on');
      }

      const dialog = bootbox.alert({
        title: `<i class='${iconMap[type] || iconMap.info} mr-2'></i> <span class='text-${type} fw-500'>${title}</span>`,
        message: message || '',
        centerVertical: true,
        className: 'modal-alert',
        closeButton: type === 'danger',
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
