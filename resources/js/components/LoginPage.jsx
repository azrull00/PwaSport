import React, { useState } from 'react';

const LoginPage = ({ onNavigate, userType, onLoginSuccess }) => {
    const [formData, setFormData] = useState({
        login: '',
        password: ''
    });
    const [errors, setErrors] = useState({});
    const [isLoading, setIsLoading] = useState(false);

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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.status === 'success') {
                // Call the success callback to handle authentication
                onLoginSuccess(data.data.token, data.data.user, userType);
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
                    onClick={() => onNavigate('onboarding')}
                    className="text-gray-600 hover:text-gray-800 transition-colors"
                >
                    <span className="text-2xl">←</span>
                </button>
                <h1 className="text-lg font-semibold text-gray-900">Masuk</h1>
                <div className="w-8"></div> {/* Spacer for centering */}
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

                    {/* Welcome Message */}
                    <div className="text-center mb-8">
                        <div className="w-20 h-20 bg-primary rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <span className="text-3xl font-bold text-white">SA</span>
                        </div>
                        <h2 className="text-2xl font-bold text-gray-900 mb-2">Selamat Datang Kembali!</h2>
                        <p className="text-gray-600">
                            Masuk untuk melanjutkan sebagai {userTypeInfo.title}
                        </p>
                    </div>

                    {/* Login Form */}
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Form Error */}
                        {errors.form && (
                            <div className="bg-red-50 border border-red-200 rounded-xl p-4">
                                <p className="text-red-600 text-sm">{errors.form}</p>
                            </div>
                        )}

                        {/* Display server validation errors */}
                        {errors.login && (
                            <div className="bg-red-50 border border-red-200 rounded-xl p-4">
                                <p className="text-red-600 text-sm">{errors.login}</p>
                            </div>
                        )}

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
                            {errors.login && !errors.form && (
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

                        {/* Forgot Password */}
                        <div className="text-right">
                            <button
                                type="button"
                                className="text-primary hover:text-primary-dark text-sm font-medium transition-colors"
                            >
                                Lupa Password?
                            </button>
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

                    {/* Divider */}
                    <div className="flex items-center my-8">
                        <div className="flex-1 border-t border-gray-300"></div>
                        <span className="px-4 text-sm text-gray-500">atau</span>
                        <div className="flex-1 border-t border-gray-300"></div>
                    </div>

                    {/* Register Link */}
                    <div className="text-center">
                        <p className="text-gray-600 mb-4">
                            Belum punya akun {userTypeInfo.title}?
                        </p>
                        <button
                            onClick={() => onNavigate('register', userType)}
                            className="w-full bg-white border-2 border-primary text-primary py-3 rounded-xl font-semibold hover:bg-primary hover:text-white transition-all duration-300 shadow-mobile"
                        >
                            Daftar sebagai {userTypeInfo.title}
                        </button>
                        
                        {/* Change User Type */}
                        <div className="mt-4">
                            <button
                                onClick={() => onNavigate('onboarding')}
                                className="text-gray-500 text-sm hover:text-gray-700 transition-colors"
                            >
                                Ingin masuk sebagai {userType === 'player' ? 'Host' : 'Player'}?
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Footer */}
            <div className="p-6 text-center">
                <p className="text-xs text-gray-500">
                    Dengan masuk, Anda menyetujui{' '}
                    <span className="text-primary">Syarat & Ketentuan</span> dan{' '}
                    <span className="text-primary">Kebijakan Privasi</span> kami
                </p>
            </div>
        </div>
    );
};

export default LoginPage; 