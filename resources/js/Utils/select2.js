export function initSelect2(element, options = {}, onChange = null) {
  const $ = window.$
  if (!element) return

  // Destroy if already initialized
  if ($(element).hasClass('select2-hidden-accessible')) {
    $(element).select2('destroy')
  }

  $(element)
    .select2(options)
    .on('change', function () {
      if (onChange) onChange($(this).val() || [])
    })

  // Set initial value if needed
  if (options.value !== undefined) {
    $(element).val(options.value).trigger('change')
  }
}

export function destroySelect2(element) {
  const $ = window.$
  if (element && $(element).hasClass('select2-hidden-accessible')) {
    $(element).select2('destroy')
  }
}