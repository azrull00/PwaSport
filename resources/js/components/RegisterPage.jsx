import React, { useState } from 'react';

const RegisterPage = ({ onNavigate, userType, onLoginSuccess }) => {
    const [formData, setFormData] = useState({
        name: '',
        first_name: '',
        last_name: '',
        email: '',
        phone_number: '',
        password: '',
        password_confirmation: '',
        date_of_birth: '',
        gender: '',
        city: '',
        district: '',
        province: '',
        country: 'Indonesia',
        user_type: userType,
        subscription_tier: 'free'
    });
    const [errors, setErrors] = useState({});
    const [isLoading, setIsLoading] = useState(false);
    const [currentStep, setCurrentStep] = useState(1);
    const totalSteps = 3;

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => {
            const updated = {
                ...prev,
                [name]: value
            };
            
            // Auto-generate name from first_name and last_name
            if (name === 'first_name' || name === 'last_name') {
                updated.name = `${name === 'first_name' ? value : prev.first_name} ${name === 'last_name' ? value : prev.last_name}`.trim();
            }
            
            return updated;
        });
        
        // Clear error when user starts typing
        if (errors[name]) {
            setErrors(prev => ({
                ...prev,
                [name]: ''
            }));
        }
    };

    const validateStep = (step) => {
        const newErrors = {};
        
        if (step === 1) {
            if (!formData.first_name.trim()) {
                newErrors.first_name = 'Nama depan harus diisi';
            }
            if (!formData.last_name.trim()) {
                newErrors.last_name = 'Nama belakang harus diisi';
            }
            if (!formData.email.trim()) {
                newErrors.email = 'Email harus diisi';
            } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
                newErrors.email = 'Format email tidak valid';
            }
            if (!formData.phone_number.trim()) {
                newErrors.phone_number = 'Nomor HP harus diisi';
            } else if (!/^[0-9+]{10,15}$/.test(formData.phone_number)) {
                newErrors.phone_number = 'Format nomor HP tidak valid. Harus berupa angka 10-15 digit';
            }
        } else if (step === 2) {
            if (!formData.password) {
                newErrors.password = 'Password harus diisi';
            } else if (formData.password.length < 8) {
                newErrors.password = 'Password minimal 8 karakter';
            }
            if (!formData.password_confirmation) {
                newErrors.password_confirmation = 'Konfirmasi password harus diisi';
            } else if (formData.password !== formData.password_confirmation) {
                newErrors.password_confirmation = 'Password tidak sama';
            }
        } else if (step === 3) {
            if (!formData.date_of_birth) {
                newErrors.date_of_birth = 'Tanggal lahir harus diisi';
            }
            if (!formData.gender) {
                newErrors.gender = 'Jenis kelamin harus dipilih';
            }
            if (!formData.city.trim()) {
                newErrors.city = 'Kota harus diisi';
            }
            if (!formData.province.trim()) {
                newErrors.province = 'Provinsi harus diisi';
            }
        }
        
        return newErrors;
    };

    const handleNext = () => {
        const newErrors = validateStep(currentStep);
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }
        
        setErrors({});
        if (currentStep < totalSteps) {
            setCurrentStep(currentStep + 1);
        }
    };

    const handlePrevious = () => {
        if (currentStep > 1) {
            setCurrentStep(currentStep - 1);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        const newErrors = validateStep(currentStep);
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setIsLoading(true);
        setErrors({});

        const requestData = {
            ...formData,
            user_type: userType // Ensure user type is included
        };
        console.log('Registration request data:', requestData);

        try {
            const response = await fetch('/api/auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            });

            const data = await response.json();
            console.log('Registration response:', data);

            if (data.status === 'success') {
                // Auto-login after successful registration
                onLoginSuccess(data.data.token, data.data.user, userType);
            } else {
                console.error('Registration failed:', data);
                if (data.errors) {
                    setErrors(data.errors);
                } else {
                    setErrors({ 
                        form: data.message || 'Pendaftaran gagal. Silakan coba lagi.' 
                    });
                }
            }
        } catch (error) {
            console.error('Registration error:', error);
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
            description: 'Bergabung dengan komunitas olahraga',
            benefits: [
                'Cari partner olahraga sesuai skill level',
                'Ikut event dan turnamen seru',
                'Rating dan review venue terpercaya'
            ]
        } : {
            icon: '🎯',
            title: 'Host',
            description: 'Kelola venue dan organisir event',
            benefits: [
                'Kelola venue olahraga Anda',
                'Organisir event dan turnamen',
                'Dapatkan penghasilan dari venue'
            ]
        };
    };

    const userTypeInfo = getUserTypeDisplay();

    const renderStep1 = () => (
        <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Informasi Pribadi</h3>
            
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label htmlFor="first_name" className="block text-sm font-medium text-gray-700 mb-1">
                        Nama Depan
                    </label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value={formData.first_name}
                        onChange={handleInputChange}
                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                            errors.first_name ? 'border-red-300 bg-red-50' : 'border-gray-300'
                        }`}
                        placeholder="Nama depan"
                    />
                    {errors.first_name && (
                        <p className="text-red-500 text-xs mt-1">{errors.first_name}</p>
                    )}
                </div>
                
                <div>
                    <label htmlFor="last_name" className="block text-sm font-medium text-gray-700 mb-1">
                        Nama Belakang
                    </label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value={formData.last_name}
                        onChange={handleInputChange}
                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                            errors.last_name ? 'border-red-300 bg-red-50' : 'border-gray-300'
                        }`}
                        placeholder="Nama belakang"
                    />
                    {errors.last_name && (
                        <p className="text-red-500 text-xs mt-1">{errors.last_name}</p>
                    )}
                </div>
            </div>
            
            <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                    Email
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                        errors.email ? 'border-red-300 bg-red-50' : 'border-gray-300'
                    }`}
                    placeholder="contoh@email.com"
                />
                {errors.email && (
                    <p className="text-red-500 text-xs mt-1">{errors.email}</p>
                )}
            </div>
            
            <div>
                <label htmlFor="phone_number" className="block text-sm font-medium text-gray-700 mb-1">
                    Nomor HP
                </label>
                <input
                    type="tel"
                    id="phone_number"
                    name="phone_number"
                    value={formData.phone_number}
                    onChange={handleInputChange}
                    className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                        errors.phone_number ? 'border-red-300 bg-red-50' : 'border-gray-300'
                    }`}
                    placeholder="081234567890"
                />
                {errors.phone_number && (
                    <p className="text-red-500 text-xs mt-1">{errors.phone_number}</p>
                )}
            </div>
        </div>
    );

    const renderStep2 = () => (
        <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Keamanan Akun</h3>
            
            <div>
                <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                    Password
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    value={formData.password}
                    onChange={handleInputChange}
                    className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                        errors.password ? 'border-red-300 bg-red-50' : 'border-gray-300'
                    }`}
                    placeholder="Minimal 8 karakter"
                />
                {errors.password && (
                    <p className="text-red-500 text-xs mt-1">{errors.password}</p>
                )}
            </div>
            
            <div>
                <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700 mb-1">
                    Konfirmasi Password
                </label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    value={formData.password_confirmation}
                    onChange={handleInputChange}
                    className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                        errors.password_confirmation ? 'border-red-300 bg-red-50' : 'border-gray-300'
                    }`}
                    placeholder="Ulangi password"
                />
                {errors.password_confirmation && (
                    <p className="text-red-500 text-xs mt-1">{errors.password_confirmation}</p>
                )}
            </div>
            
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-blue-700 text-sm">
                    <strong>Tips keamanan:</strong> Gunakan kombinasi huruf besar, huruf kecil, angka, dan simbol untuk password yang kuat.
                </p>
            </div>
        </div>
    );

    const renderStep3 = () => (
        <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Informasi Tambahan</h3>
            
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label htmlFor="date_of_birth" className="block text-sm font-medium text-gray-700 mb-1">
                        Tanggal Lahir
                    </label>
                    <input
                        type="date"
                        id="date_of_birth"
                        name="date_of_birth"
                        value={formData.date_of_birth}
                        onChange={handleInputChange}
                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                            errors.date_of_birth ? 'border-red-300 bg-red-50' : 'border-gray-300'
                        }`}
                    />
                    {errors.date_of_birth && (
                        <p className="text-red-500 text-xs mt-1">{errors.date_of_birth}</p>
                    )}
                </div>
                
                <div>
                    <label htmlFor="gender" className="block text-sm font-medium text-gray-700 mb-1">
                        Jenis Kelamin
                    </label>
                    <select
                        id="gender"
                        name="gender"
                        value={formData.gender}
                        onChange={handleInputChange}
                        className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                            errors.gender ? 'border-red-300 bg-red-50' : 'border-gray-300'
                        }`}
                    >
                        <option value="">Pilih</option>
                        <option value="male">Laki-laki</option>
                        <option value="female">Perempuan</option>
                    </select>
                    {errors.gender && (
                        <p className="text-red-500 text-xs mt-1">{errors.gender}</p>
                    )}
                </div>
            </div>
            
            <div>
                <label htmlFor="city" className="block text-sm font-medium text-gray-700 mb-1">
                    Kota
                </label>
                <input
                    type="text"
                    id="city"
                    name="city"
                    value={formData.city}
                    onChange={handleInputChange}
                    className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                        errors.city ? 'border-red-300 bg-red-50' : 'border-gray-300'
                    }`}
                    placeholder="Nama kota"
                />
                {errors.city && (
                    <p className="text-red-500 text-xs mt-1">{errors.city}</p>
                )}
            </div>
            
            <div>
                <label htmlFor="province" className="block text-sm font-medium text-gray-700 mb-1">
                    Provinsi
                </label>
                <input
                    type="text"
                    id="province"
                    name="province"
                    value={formData.province}
                    onChange={handleInputChange}
                    className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent ${
                        errors.province ? 'border-red-300 bg-red-50' : 'border-gray-300'
                    }`}
                    placeholder="Nama provinsi"
                />
                {errors.province && (
                    <p className="text-red-500 text-xs mt-1">{errors.province}</p>
                )}
            </div>

            {/* User Type Benefits */}
            <div className="bg-primary/5 border border-primary/20 rounded-lg p-4 mt-6">
                <h4 className="font-semibold text-primary mb-2">Keuntungan sebagai {userTypeInfo.title}:</h4>
                <div className="space-y-1">
                    {userTypeInfo.benefits.map((benefit, index) => (
                        <div key={index} className="flex items-center text-sm text-gray-700">
                            <span className="text-green-500 mr-2">✓</span>
                            <span>{benefit}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-secondary flex flex-col">
            {/* Header */}
            <div className="flex items-center justify-between p-6">
                <button 
                    onClick={() => currentStep === 1 ? onNavigate('onboarding') : handlePrevious()}
                    className="text-gray-600 hover:text-gray-800 transition-colors"
                >
                    <span className="text-2xl">←</span>
                </button>
                <h1 className="text-lg font-semibold text-gray-900">Daftar Akun</h1>
                <div className="w-8"></div>
            </div>

            {/* User Type Badge */}
            <div className="px-6 pb-4">
                <div className="flex justify-center">
                    <div className="bg-primary text-white px-6 py-3 rounded-2xl shadow-mobile">
                        <div className="flex items-center space-x-3">
                            <span className="text-2xl">{userTypeInfo.icon}</span>
                            <div className="text-left">
                                <div className="font-semibold text-base">Mendaftar sebagai {userTypeInfo.title}</div>
                                <div className="text-blue-100 text-xs">{userTypeInfo.description}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                {/* Change User Type Link */}
                <div className="text-center mt-3">
                    <button
                        onClick={() => onNavigate('onboarding')}
                        className="text-gray-500 text-sm hover:text-gray-700 transition-colors"
                    >
                        Ingin daftar sebagai {userType === 'player' ? 'Host' : 'Player'}?
                    </button>
                </div>
            </div>

            {/* Progress Bar */}
            <div className="px-6 pb-4">
                <div className="flex items-center justify-between mb-2">
                    <span className="text-sm text-gray-600">Langkah {currentStep} dari {totalSteps}</span>
                    <span className="text-sm text-gray-600">{Math.round((currentStep / totalSteps) * 100)}%</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                        className="bg-primary h-2 rounded-full transition-all duration-300" 
                        style={{ width: `${(currentStep / totalSteps) * 100}%` }}
                    ></div>
                </div>
            </div>

            {/* Content */}
            <div className="flex-1 px-6 py-4">
                <div className="max-w-sm mx-auto">
                    {/* Welcome Message */}
                    <div className="text-center mb-6">
                        <div className="w-16 h-16 bg-primary rounded-2xl flex items-center justify-center mx-auto mb-3">
                            <span className="text-2xl font-bold text-white">SA</span>
                        </div>
                        <h2 className="text-xl font-bold text-gray-900 mb-2">Bergabung sebagai {userTypeInfo.title}</h2>
                        <p className="text-gray-600 text-sm">
                            Lengkapi informasi Anda untuk membuat akun
                        </p>
                    </div>

                    {/* Form */}
                    <form onSubmit={currentStep === totalSteps ? handleSubmit : (e) => { e.preventDefault(); handleNext(); }}>
                        {/* Form Error */}
                        {errors.form && (
                            <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                                <p className="text-red-600 text-sm">{errors.form}</p>
                            </div>
                        )}

                        {/* Step Content */}
                        {currentStep === 1 && renderStep1()}
                        {currentStep === 2 && renderStep2()}
                        {currentStep === 3 && renderStep3()}

                        {/* Navigation Buttons */}
                        <div className="flex justify-between items-center mt-8 space-x-4">
                            {currentStep > 1 && (
                                <button
                                    type="button"
                                    onClick={handlePrevious}
                                    className="px-6 py-3 rounded-xl font-medium text-gray-600 hover:text-gray-800 transition-colors"
                                >
                                    Kembali
                                </button>
                            )}
                            
                            <button
                                type="submit"
                                disabled={isLoading}
                                className={`flex-1 py-3 rounded-xl font-semibold text-white transition-all duration-300 shadow-mobile ${
                                    isLoading
                                        ? 'bg-gray-400 cursor-not-allowed'
                                        : 'bg-primary hover:bg-primary-dark hover:shadow-lg transform hover:-translate-y-1'
                                }`}
                            >
                                {isLoading ? `Sedang Mendaftar sebagai ${userTypeInfo.title}...` : (currentStep === totalSteps ? `Daftar sebagai ${userTypeInfo.title}` : 'Lanjut')}
                            </button>
                        </div>
                    </form>

                    {/* Login Link */}
                    <div className="text-center mt-6">
                        <p className="text-gray-600 text-sm">
                            Sudah punya akun {userTypeInfo.title}?{' '}
                            <button
                                onClick={() => onNavigate('login', userType)}
                                className="text-primary hover:text-primary-dark font-medium transition-colors"
                            >
                                Masuk di sini
                            </button>
                        </p>
                    </div>
                </div>
            </div>

            {/* Footer */}
            <div className="p-6 text-center">
                <p className="text-xs text-gray-500">
                    Dengan mendaftar, Anda menyetujui{' '}
                    <span className="text-primary">Syarat & Ketentuan</span> dan{' '}
                    <span className="text-primary">Kebijakan Privasi</span> kami
                </p>
            </div>
        </div>
    );
};

export default RegisterPage; 