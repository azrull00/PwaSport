# SportPWA - Sports Community Platform

A Progressive Web App (PWA) built with Laravel 12, React 19, and Tailwind CSS 4 for connecting sports players and event hosts.

## 🚀 Features

- **Mobile-First Design**: Optimized for mobile devices with responsive design for tablets
- **Progressive Web App**: Installable on mobile devices with offline capabilities
- **User Types**: Support for both Players and Event Hosts
- **Modern UI**: Built with Tailwind CSS 4 and custom SportPWA theme
- **React 19**: Latest React with modern hooks and components
- **Laravel 12**: Robust backend API with comprehensive sports management features

## 🎨 Frontend Pages

### 1. Onboarding Page
- 3-slide carousel introduction
- Sports-themed content with emojis
- Role selection (Player vs Host)
- Mobile-optimized navigation

### 2. Login Page
- User type differentiation
- Password visibility toggle
- Form validation and error handling
- Social login options (placeholder)

### 3. Register Page
- Comprehensive registration form
- Real-time validation
- Terms and conditions acceptance
- User type-specific styling

## 🛠️ Technology Stack

### Frontend
- **React 19.1.0** - Modern JavaScript framework
- **Tailwind CSS 4.0.0** - Utility-first CSS framework
- **Vite 6.2.4** - Fast build tool and development server
- **TypeScript Support** - Type-safe development

### Backend
- **Laravel 12** - PHP framework
- **Laravel Sanctum** - API authentication
- **MySQL** - Database
- **Broadcasting** - Real-time features

## 🎯 Custom Theme

The SportPWA uses a custom color scheme:
- **Primary**: #448EF7 (Blue)
- **Secondary**: #F1F8FB (Light Blue/Gray)
- **Accent**: #FF6B35 (Orange)
- **Success**: #28A745 (Green)
- **Warning**: #FFC107 (Yellow)
- **Error**: #DC3545 (Red)

## 📱 PWA Features

- **Offline Capability**: Service worker caching
- **Installable**: Add to home screen
- **Push Notifications**: Real-time updates
- **Background Sync**: Offline action handling
- **App-like Experience**: Standalone display mode

## 🚀 Getting Started

### Prerequisites
- Node.js 18+ and npm
- PHP 8.2+
- Composer
- MySQL

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/sportpwa.git
   cd sportpwa
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Build frontend assets**
   ```bash
   npm run build
   ```

### Development

1. **Start Laravel development server**
   ```bash
   php artisan serve
   ```

2. **Start Vite development server (in another terminal)**
   ```bash
   npm run dev
   ```

3. **Access the application**
   - Frontend: http://localhost:8000
   - API: http://localhost:8000/api

## 📁 Project Structure

```
SportPWA/
├── resources/
│   ├── js/
│   │   ├── app.jsx                 # Main React app
│   │   └── components/
│   │       ├── OnboardingPage.jsx  # Welcome slides
│   │       ├── LoginPage.jsx       # User login
│   │       └── RegisterPage.jsx    # User registration
│   ├── css/
│   │   └── app.css                 # Tailwind + custom styles
│   └── views/
│       └── welcome.blade.php       # Laravel view with React mount
├── public/
│   ├── manifest.json               # PWA manifest
│   ├── sw.js                       # Service worker
│   └── [icon files]                # PWA icons (placeholders)
├── app/
│   ├── Http/Controllers/Api/       # API controllers
│   ├── Models/                     # Eloquent models
│   └── Services/                   # Business logic
└── database/
    ├── migrations/                 # Database schema
    └── seeders/                    # Sample data
```

## 🎯 Next Steps (Backend Integration)

To complete the SportPWA, you'll need to:

1. **Update API endpoints** in React components:
   - Replace placeholder API calls with actual Laravel routes
   - Implement proper error handling
   - Add loading states

2. **Add authentication flow**:
   - Laravel Sanctum token management
   - Protected routes
   - User session handling

3. **Create additional pages**:
   - Dashboard (Player/Host specific)
   - Event listing and creation
   - Profile management
   - Chat/messaging

4. **Add real icons**:
   - Replace placeholder icon files with actual SportPWA logos
   - Generate proper PWA icons in multiple sizes

5. **Implement bottom navigation**:
   - Create navigation component
   - Add routing between main sections

## 🔧 Available Scripts

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run preview` - Preview production build
- `php artisan serve` - Start Laravel server
- `php artisan migrate` - Run database migrations
- `php artisan db:seed` - Seed database with sample data

## 📱 PWA Installation

Users can install the SportPWA on their devices:
1. Open the app in a mobile browser
2. Look for "Add to Home Screen" prompt
3. Follow the installation steps
4. Launch from home screen like a native app

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🙏 Acknowledgments

- Laravel Team for the amazing framework
- React Team for the powerful library
- Tailwind CSS for the utility-first approach
- The sports community for inspiration
