export function initSelect2(element, options = {}, onChange = null) {
  const $ = window.$
  if (!element) return

  // Destroy if already initialized
  if ($(element).hasClass('select2-hidden-accessible')) {
    $(element).select2('destroy').off('select2:select select2:unselect change')
  }

  // Initialize Select2 with provided options
  $(element).select2({
    ...options,
  })

  // Bind Select2-specific events
  $(element).on('select2:select select2:unselect change', function () {
    const value = $(this).val()
    if (onChange) {
      onChange(value || null) // Use null for cleared selections
    }
  })

  // Set initial value if provided
  if (options.value !== undefined && options.value !== null) {
    $(element).val(options.value).trigger('change')
  }
}

export function destroySelect2(element) {
  const $ = window.$
  if (element && $(element).hasClass('select2-hidden-accessible')) {
    $(element).off('select2:select select2:unselect change').select2('destroy')
  }
}