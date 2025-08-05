// resources/js/Utils/dateFormat.js

/**
 * Format date to "Aug 05, 2025"
 */
export function formatDateShort(date) {
    if (!date) return ''
    try {
        const dt = new Date(date)
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
        }).format(dt)
    } catch (error) {
        console.error('Invalid date:', date)
        return ''
    }
}

/**
 * Format date to "Aug 05, 2025, 3:45 PM"
 */
export function formatDateWithTime(date) {
    if (!date) return ''
    try {
        const dt = new Date(date)
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(dt)
    } catch (error) {
        console.error('Invalid date:', date)
        return ''
    }
}
