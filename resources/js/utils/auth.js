/**
 * Safely parse JWT token to extract user information
 * @param {string} token - JWT token string
 * @returns {object|null} - Parsed payload or null if invalid
 */
export const parseJWTToken = (token) => {
    try {
        if (!token || typeof token !== 'string') {
            return null;
        }

        const tokenParts = token.split('.');
        if (tokenParts.length !== 3) {
            return null;
        }

        const payload = JSON.parse(atob(tokenParts[1]));
        return payload;
    } catch (error) {
        console.error('Error parsing JWT token:', error);
        return null;
    }
};

/**
 * Get user ID from JWT token
 * @param {string} token - JWT token string
 * @returns {number|null} - User ID or null if invalid
 */
export const getUserIdFromToken = (token) => {
    const payload = parseJWTToken(token);
    return payload?.sub || null;
};

/**
 * Check if JWT token is valid and not expired
 * @param {string} token - JWT token string
 * @returns {boolean} - True if valid and not expired
 */
export const isTokenValid = (token) => {
    const payload = parseJWTToken(token);
    if (!payload) {
        return false;
    }

    // Check if token has expiration time and if it's not expired
    if (payload.exp) {
        const currentTime = Math.floor(Date.now() / 1000);
        return payload.exp > currentTime;
    }

    // If no expiration time, consider it valid for now
    return true;
}; 