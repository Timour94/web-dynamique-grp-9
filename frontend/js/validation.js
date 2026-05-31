function isValidEmail(email){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email); }
function isPositiveNumber(value){ return !Number.isNaN(Number(value)) && Number(value) >= 0; }
