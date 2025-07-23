import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

const OnboardingPage = () => {
    const navigate = useNavigate();
    const [currentSlide, setCurrentSlide] = useState(0);
    const [showUserTypeSelection, setShowUserTypeSelection] = useState(false);

    const slides = [
        {
            id: 1,
            title: "Temukan Partner Olahraga",
            subtitle: "Bergabunglah dengan komunitas olahraga lokal",
            description: "Cari teman bermain untuk berbagai jenis olahraga di sekitar Anda. Dari badminton hingga sepak bola, temukan partner yang tepat untuk aktivitas olahraga favorit Anda.",
            image: "🏸",
            features: [
                "Cari partner berdasarkan skill level",
                "Bergabung dengan grup olahraga lokal",
                "Chat langsung dengan anggota komunitas"
            ]
        },
        {
            id: 2,
            title: "Booking Lapangan Mudah",
            subtitle: "Sewa venue olahraga dengan praktis",
            description: "Pesan lapangan olahraga favorit Anda dengan mudah. Lihat ketersediaan real-time, bandingkan harga, dan booking langsung melalui aplikasi.",
            image: "🏟️",
            features: [
                "Lihat jadwal lapangan real-time",
                "Pembayaran digital yang aman",
                "Rating dan review venue terpercaya"
            ]
        },
        {
            id: 3,
            title: "Turnamen & Event",
            subtitle: "Ikut kompetisi dan menangkan hadiah",
            description: "Bergabunglah dalam turnamen lokal dan event olahraga menarik. Tingkatkan skill Anda sambil berkompetisi dengan pemain lain di komunitas.",
            image: "🏆",
            features: [
                "Turnamen mingguan berbagai olahraga",
                "Sistem ranking dan leaderboard",
                "Hadiah menarik untuk juara"
            ]
        },
        {
            id: 4,
            title: "Mulai Petualangan Olahraga",
            subtitle: "Pilih peran Anda di SportApp",
            description: "Bergabunglah sebagai Player untuk mencari partner olahraga, atau sebagai Host untuk mengorganisir event dan menyediakan venue.",
            image: "🚀",
            features: [
                "Gratis untuk semua pengguna",
                "Tersedia di seluruh Indonesia",
                "Dukungan customer service 24/7"
            ]
        }
    ];

    const nextSlide = () => {
        if (currentSlide < slides.length - 1) {
            setCurrentSlide(currentSlide + 1);
        } else {
            // Pada slide terakhir, tampilkan user type selection
            setShowUserTypeSelection(true);
        }
    };

    const prevSlide = () => {
        if (showUserTypeSelection) {
            setShowUserTypeSelection(false);
        } else if (currentSlide > 0) {
            setCurrentSlide(currentSlide - 1);
        }
    };

    const goToSlide = (index) => {
        setCurrentSlide(index);
        setShowUserTypeSelection(false);
    };

    const handleUserTypeSelection = (action, userType) => {
        // Store user type selection in localStorage for the target page
        localStorage.setItem('selectedUserType', userType);
        
        // Navigate to the appropriate page
        if (action === 'login') {
            navigate('/login');
        } else if (action === 'register') {
            navigate('/register');
        }
    };

    const isLastSlide = currentSlide === slides.length - 1;
    const isFirstSlide = currentSlide === 0;

    // User Type Selection Screen
    if (showUserTypeSelection) {
        return (
            <div className="min-h-screen bg-gradient-to-br from-[#448EF7] to-[#6366f1] flex flex-col">
                {/* Header */}
                <div className="flex justify-between items-center p-6">
                    <button 
                        onClick={prevSlide}
                        className="text-white/80 hover:text-white transition-colors"
                    >
                        <span className="text-2xl">←</span>
                    </button>
                    <div className="text-white font-bold text-xl">SportApp</div>
                    <div className="w-8"></div>
                </div>

                {/* Content */}
                <div className="flex-1 flex flex-col items-center justify-center px-6">
                    <div className="w-full max-w-md text-center">
                        {/* Icon */}
                        <div className="text-6xl mb-6">🎯</div>
                        
                        {/* Title */}
                        <h1 className="text-3xl font-bold text-white mb-3">
                            Pilih Peran Anda
                        </h1>
                        
                        {/* Subtitle */}
                        <p className="text-blue-100 text-base leading-relaxed mb-8 opacity-90">
                            Bagaimana Anda ingin menggunakan SportApp?
                        </p>

                        {/* User Type Cards */}
                        <div className="space-y-4 mb-8">
                            {/* Player Card */}
                            <div className="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20">
                                <div className="text-4xl mb-3">🏃‍♂️</div>
                                <h3 className="text-xl font-bold text-white mb-2">Player</h3>
                                <p className="text-blue-100 text-sm mb-4">
                                    Cari partner olahraga, ikut event, dan bergabung dengan komunitas
                                </p>
                                <div className="space-y-2">
                                    <button
                                        onClick={() => handleUserTypeSelection('register', 'player')}
                                        className="w-full bg-white text-[#448EF7] py-3 rounded-xl font-semibold hover:bg-gray-100 transition-all duration-300 shadow-lg"
                                    >
                                        Daftar sebagai Player
                                    </button>
                                    <button
                                        onClick={() => handleUserTypeSelection('login', 'player')}
                                        className="w-full bg-white/20 text-white py-3 rounded-xl font-medium hover:bg-white/30 transition-all duration-300 backdrop-blur-sm"
                                    >
                                        Masuk sebagai Player
                                    </button>
                                </div>
                            </div>

                            {/* Host Card */}
                            <div className="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20">
                                <div className="text-4xl mb-3">🎯</div>
                                <h3 className="text-xl font-bold text-white mb-2">Host</h3>
                                <p className="text-blue-100 text-sm mb-4">
                                    Kelola venue, organisir event, dan bangun komunitas olahraga
                                </p>
                                <div className="space-y-2">
                                    <button
                                        onClick={() => handleUserTypeSelection('register', 'host')}
                                        className="w-full bg-green-500 text-white py-3 rounded-xl font-semibold hover:bg-green-600 transition-all duration-300 shadow-lg"
                                    >
                                        Daftar sebagai Host
                                    </button>
                                    <button
                                        onClick={() => handleUserTypeSelection('login', 'host')}
                                        className="w-full bg-white/20 text-white py-3 rounded-xl font-medium hover:bg-white/30 transition-all duration-300 backdrop-blur-sm"
                                    >
                                        Masuk sebagai Host
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    // Main Onboarding Slides
    return (
        <div className="min-h-screen bg-gradient-to-br from-[#448EF7] to-[#6366f1] flex flex-col">
            {/* Header dengan Logo */}
            <div className="flex justify-between items-center p-6">
                <div className="text-white font-bold text-xl">SportApp</div>
                <div className="text-white text-sm opacity-75">
                    {currentSlide + 1} dari {slides.length}
                </div>
            </div>

            {/* Content Area */}
            <div className="flex-1 flex flex-col items-center justify-center px-6 pb-8">
                <div className="w-full max-w-md text-center">
                    {/* Image/Icon */}
                    <div className="text-8xl mb-6 animate-bounce">
                        {slides[currentSlide].image}
                    </div>

                    {/* Title */}
                    <h1 className="text-3xl font-bold text-white mb-3">
                        {slides[currentSlide].title}
                    </h1>

                    {/* Subtitle */}
                    <h2 className="text-lg text-blue-100 mb-4 font-medium">
                        {slides[currentSlide].subtitle}
                    </h2>

                    {/* Description */}
                    <p className="text-blue-100 text-base leading-relaxed mb-6 opacity-90">
                        {slides[currentSlide].description}
                    </p>

                    {/* Features List */}
                    <div className="bg-white/10 backdrop-blur-sm rounded-2xl p-4 mb-8">
                        <div className="space-y-3">
                            {slides[currentSlide].features.map((feature, index) => (
                                <div key={index} className="flex items-center text-white text-sm">
                                    <span className="text-green-300 mr-3 text-lg">✓</span>
                                    <span>{feature}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Slide Indicators */}
                    <div className="flex justify-center space-x-2 mb-8">
                        {slides.map((_, index) => (
                            <button
                                key={index}
                                onClick={() => goToSlide(index)}
                                className={`w-3 h-3 rounded-full transition-all duration-300 ${
                                    currentSlide === index 
                                        ? 'bg-white scale-125' 
                                        : 'bg-white/40 hover:bg-white/60'
                                }`}
                            />
                        ))}
                    </div>
                </div>
            </div>

            {/* Navigation Buttons */}
            <div className="px-6 pb-8">
                <div className="flex justify-between items-center space-x-4 max-w-md mx-auto w-full">
                    {/* Previous Button */}
                    <button
                        onClick={prevSlide}
                        disabled={isFirstSlide}
                        className={`px-6 py-3 rounded-full font-medium transition-all duration-300 ${
                            isFirstSlide
                                ? 'bg-white/20 text-white/50 cursor-not-allowed'
                                : 'bg-white/30 text-white hover:bg-white/40 backdrop-blur-sm'
                        }`}
                    >
                        Kembali
                    </button>

                    {/* Next Button */}
                    <button
                        onClick={nextSlide}
                        className="flex-1 bg-white text-[#448EF7] py-3 rounded-full font-semibold hover:bg-gray-100 transition-all duration-300 shadow-lg"
                    >
                        {isLastSlide ? 'Mulai' : 'Lanjut'}
                    </button>
                </div>

                {/* Skip Button */}
                {!isLastSlide && (
                    <div className="text-center mt-4">
                        <button
                            onClick={() => setShowUserTypeSelection(true)}
                            className="text-white/70 text-sm hover:text-white transition-all duration-300 underline"
                        >
                            Lewati dan Mulai Sekarang
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
};

export default OnboardingPage; 