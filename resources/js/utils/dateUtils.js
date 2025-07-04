/**
 * Format time string from 24-hour format to 12-hour format
 * @param {string} time - Time in 24-hour format (HH:mm)
 * @returns {string} Time in 12-hour format with AM/PM
 */
export const formatTime = (time) => {
  if (!time) return '';
  
  const [hours, minutes] = time.split(':');
  const hour = parseInt(hours, 10);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const formattedHour = hour % 12 || 12;
  
  return `${formattedHour}:${minutes} ${ampm}`;
};

/**
 * Get day of week in lowercase
 * @returns {string} Current day of week in lowercase
 */
export const getCurrentDayOfWeek = () => {
  const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
  return days[new Date().getDay()];
};

/**
 * Format date to locale string
 * @param {Date|string} date - Date object or date string
 * @returns {string} Formatted date string
 */
export const formatDate = (date) => {
  const d = new Date(date);
  return d.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};

/**
 * Check if a time is between two times
 * @param {string} time - Time to check in HH:mm format
 * @param {string} start - Start time in HH:mm format
 * @param {string} end - End time in HH:mm format
 * @returns {boolean} True if time is between start and end
 */
export const isTimeBetween = (time, start, end) => {
  const [timeHours, timeMinutes] = time.split(':').map(Number);
  const [startHours, startMinutes] = start.split(':').map(Number);
  const [endHours, endMinutes] = end.split(':').map(Number);
  
  const timeValue = timeHours * 60 + timeMinutes;
  const startValue = startHours * 60 + startMinutes;
  const endValue = endHours * 60 + endMinutes;
  
  return timeValue >= startValue && timeValue <= endValue;
}; 