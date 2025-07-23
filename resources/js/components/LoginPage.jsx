import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

const LoginPage = ({ onLoginSuccess }) => {
    const navigate = useNavigate();
    const [userType, setUserType] = useState('player');
    const [formData, setFormData] = useState({
        login: '',
        password: ''
    });
    const [errors, setErrors] = useState({});
    const [isLoading, setIsLoading] = useState(false);

    // Get user type from localStorage if available
    useEffect(() => {
        const selectedUserType = localStorage.getItem('selectedUserType');
        if (selectedUserType) {
            setUserType(selectedUserType);
        }
    }, []);

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
        // Clear error when user starts typing
        if (errors[name]) {
            setErrors(prev => ({
                ...prev,
                [name]: ''
            }));
        }
    };

    const validateForm = () => {
        const newErrors = {};
        
        if (!formData.login.trim()) {
            newErrors.login = 'Email atau nomor HP harus diisi';
        }
        
        if (!formData.password) {
            newErrors.password = 'Password harus diisi';
        } else if (formData.password.length < 6) {
            newErrors.password = 'Password minimal 6 karakter';
        }
        
        return newErrors;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        const newErrors = validateForm();
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setIsLoading(true);
        setErrors({});

        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({
                    ...formData,
                    user_type: userType // Send user type with login request
                })
            });

            const data = await response.json();

            if (data.status === 'success' && data.data?.user) {
                // Verify user type matches
                const isHost = data.data.user.is_host;
                if ((userType === 'host' && !isHost) || (userType === 'player' && isHost)) {
                    setErrors({
                        form: userType === 'host' 
                            ? 'Akun ini bukan akun Host. Silakan login sebagai Player.' 
                            : 'Akun ini adalah akun Host. Silakan login sebagai Host.'
                    });
                    return;
                }

                // Call the success callback to handle authentication
                const actualUserType = isHost ? 'host' : 'player';
                
                // Store auth data in localStorage
                localStorage.setItem('authToken', data.data.token);
                localStorage.setItem('userData', JSON.stringify(data.data.user));
                localStorage.setItem('userType', actualUserType);
                
                // Check if onLoginSuccess is provided before calling it
                if (typeof onLoginSuccess === 'function') {
                    onLoginSuccess(actualUserType, data.data.token, data.data.user);
                }
                
                // Navigate to the appropriate dashboard
                navigate(actualUserType === 'host' ? '/host' : '/player');
            } else {
                // Handle validation errors
                if (data.errors) {
                    setErrors(data.errors);
                } else {
                    setErrors({ 
                        form: data.message || 'Login gagal. Silakan coba lagi.' 
                    });
                }
            }
        } catch (error) {
            console.error('Login error:', error);
            setErrors({ 
                form: 'Terjadi kesalahan. Silakan coba lagi.' 
            });
        } finally {
            setIsLoading(false);
        }
    };

    const getUserTypeDisplay = () => {
        return userType === 'player' ? {
            icon: '🏃‍♂️',
            title: 'Player',
            description: 'Bergabung dengan komunitas olahraga'
        } : {
            icon: '🎯',
            title: 'Host',
            description: 'Kelola venue dan organisir event'
        };
    };

    const userTypeInfo = getUserTypeDisplay();

    return (
        <div className="min-h-screen bg-secondary flex flex-col">
            {/* Header */}
            <div className="flex items-center justify-between p-6">
                <button 
                    onClick={() => navigate('/onboarding')}
                    className="text-gray-600 hover:text-gray-800 transition-colors"
                >
                    <span className="text-2xl">←</span>
                </button>
                <h1 className="text-lg font-semibold text-gray-900">Masuk</h1>
                <div className="w-8"></div>
            </div>

            {/* Content */}
            <div className="flex-1 px-6 py-8">
                <div className="max-w-sm mx-auto">
                    {/* User Type Badge */}
                    <div className="flex justify-center mb-6">
                        <div className="bg-primary text-white px-6 py-3 rounded-2xl shadow-mobile">
                            <div className="flex items-center space-x-3">
                                <span className="text-2xl">{userTypeInfo.icon}</span>
                                <div className="text-left">
                                    <div className="font-semibold text-base">Masuk sebagai {userTypeInfo.title}</div>
                                    <div className="text-blue-100 text-xs">{userTypeInfo.description}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Form Error */}
                    {errors.form && (
                        <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                            <p className="text-red-600 text-sm">{errors.form}</p>
                        </div>
                    )}

                    {/* Login Form */}
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Email/Phone Field */}
                        <div>
                            <label htmlFor="login" className="block text-sm font-medium text-gray-700 mb-2">
                                Email atau Nomor HP
                            </label>
                            <input
                                type="text"
                                id="login"
                                name="login"
                                value={formData.login}
                                onChange={handleInputChange}
                                className={`w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 ${
                                    errors.login ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                }`}
                                placeholder="Masukkan email atau nomor HP"
                            />
                            {errors.login && (
                                <p className="text-red-500 text-sm mt-1">{errors.login}</p>
                            )}
                        </div>

                        {/* Password Field */}
                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-2">
                                Password
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                value={formData.password}
                                onChange={handleInputChange}
                                className={`w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 ${
                                    errors.password ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                }`}
                                placeholder="Masukkan password"
                            />
                            {errors.password && (
                                <p className="text-red-500 text-sm mt-1">{errors.password}</p>
                            )}
                        </div>

                        {/* Submit Button */}
                        <button
                            type="submit"
                            disabled={isLoading}
                            className={`w-full py-3 rounded-xl font-semibold text-white transition-all duration-300 shadow-mobile ${
                                isLoading
                                    ? 'bg-gray-400 cursor-not-allowed'
                                    : 'bg-primary hover:bg-primary-dark hover:shadow-lg transform hover:-translate-y-1'
                            }`}
                        >
                            {isLoading ? `Sedang Masuk sebagai ${userTypeInfo.title}...` : `Masuk sebagai ${userTypeInfo.title}`}
                        </button>
                    </form>

                    {/* Register Link */}
                    <div className="text-center mt-8">
                        <p className="text-gray-600 mb-4">
                            Belum punya akun {userTypeInfo.title}?
                        </p>
                        <button
                            onClick={() => navigate('/register')}
                            className="w-full bg-white border-2 border-primary text-primary py-3 rounded-xl font-semibold hover:bg-primary hover:text-white transition-all duration-300 shadow-mobile"
                        >
                            Daftar sebagai {userTypeInfo.title}
                        </button>
                        
                        {/* Change User Type */}
                        <div className="mt-4">
                            <button
                                onClick={() => navigate('/onboarding')}
                                className="text-gray-500 text-sm hover:text-gray-700 transition-colors"
                            >
                                Ingin masuk sebagai {userType === 'player' ? 'Host' : 'Player'}?
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default LoginPage;