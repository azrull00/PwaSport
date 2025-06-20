# RacketHub - Sport Community PWA

## 🎯 **Project Overview**

RacketHub adalah Progressive Web Application (PWA) yang menghubungkan para pemain olahraga raket untuk bermain bersama, membentuk komunitas, dan meningkatkan kualitas permainan melalui sistem rating dan match history.

### 🏆 **Supported Sports**
- **Badminton** 🏸
- **Tennis** 🎾  
- **Paddle Tennis** 🏓
- **Squash** 🎯
- **Table Tennis** 🏓
- **Pickleball** 🥎

## 📊 **Development Status: Phase 3 (API Development)**

### ✅ **COMPLETED (Phase 1-2)**
- **Database Foundation:** 17 tables with relationships
- **Authentication System:** Laravel Sanctum + role management
- **Core Models:** User, Sport, Event, Community, Notifications
- **Sports Data:** 6 racket sports seeded
- **Email Notification System:** Complete email + in-app notifications

### 🚧 **IN PROGRESS (Phase 3)**
- **API Controllers:** Auth ✅, Sport ✅, Event ⏳, User ⏳
- **Form Validation:** Registration ✅, Login ✅
- **Notification Service:** Email implementation ✅

## 🔧 **Technical Architecture**

### **Backend Stack**
- **Framework:** Laravel 11
- **Database:** MySQL 8.0
- **Authentication:** Laravel Sanctum (API tokens)
- **Permissions:** Spatie Laravel Permission
- **Real-time:** Pusher (planned)
- **Email:** Laravel Mail + SMTP
- **WhatsApp:** Fonnte integration (planned)

### **Notification Strategy** 
- ✅ **Email Notifications:** Event reminders, match results, credit score changes
- ✅ **In-App Notifications:** Database-stored notifications with read status
- ❌ **Web Push:** Removed - focusing on email + in-app only

### **Database Schema (17 Tables)**
```
Core Tables:
├── users (authentication + credit score)
├── user_profiles (personal info + QR codes)
├── sports (6 racket sports)
└── user_sport_ratings (MMR + skill levels)

Community System:
├── communities (sport-based communities)
├── events (4 types: Mabar, Coaching, Friendly, Tournament)
├── event_participants (registration + queue system)
└── community_ratings (community feedback)

Match System:
├── match_history (game results)
├── player_ratings (skill + sportsmanship + punctuality)
└── credit_score_logs (behavior tracking)

Social Features:
├── notifications (in-app messages)
├── user_blocks (player blocking)
└── Permission tables (Spatie)
```

## 🎮 **Core Features**

### **User Management**
- **Registration:** Email/phone + profile creation
- **Authentication:** Email or phone login via API
- **Profile System:** Personal info, QR codes, location data
- **Credit Score:** Behavior-based scoring (100 default)
- **Sport Ratings:** Individual MMR per sport

### **Event System** 
- **4 Event Types:**
  - 🏸 **Mabar** (Casual play)
  - 👨‍🏫 **Coaching** (Training sessions)
  - 🤝 **Friendly Match** (Competitive friendly)
  - 🏆 **Tournament** (Organized competition)

- **Smart Registration:**
  - Automatic confirmation or queue system
  - Premium player protection
  - Skill level matching
  - Location-based filtering

### **Community Features**
- **Sport-based Communities:** Organized by sport type
- **Host Management:** Community leaders manage events
- **Rating System:** Community feedback from participants
- **Blocking System:** User safety features

### **Match & Rating System**
- **Host Score Input:** Event organizers input match results
- **3-Point Rating System:**
  - 🎯 **Skill Level** (1-5)
  - 🤝 **Sportsmanship** (1-5) 
  - ⏰ **Punctuality** (1-5)
- **MMR Calculation:** Automatic skill level adjustments

### **Credit Score System**
- **Behavior Tracking:** No-shows, late cancellations, good behavior
- **Automatic Penalties:** -20 points for no-show, -10 for late cancel
- **Recovery Mechanism:** +5 points for good participation
- **Queue Priority:** Higher credit score = better queue position

## 📱 **Notification System**

### **Email Notifications**
- Event reminders (24h before)
- Match result notifications
- Rating received alerts
- Credit score changes
- Event cancellations
- Waitlist promotions
- New event announcements
- Community invitations

### **In-App Notifications**
- Real-time notification center
- Read/unread status tracking
- Rich data payload for context
- Notification history

## 🔄 **User Journey Flow**

### **New User Registration**
1. **Sign Up:** Email/phone + password + basic info
2. **Profile Setup:** Personal details + location + emergency contact
3. **Sport Selection:** Choose preferred sports + skill levels
4. **QR Code Generation:** Automatic unique identifier creation
5. **Community Discovery:** Browse nearby communities

### **Event Participation**
1. **Browse Events:** Filter by sport, location, skill level, type
2. **Register:** Instant confirmation or join waiting list
3. **Preparation:** Receive email reminder 24h before
4. **Check-in:** QR code scan at venue
5. **Play & Rate:** Participate + rate other players
6. **Credit Score:** Automatic behavior tracking

## 🛡️ **Security Features**

### **User Safety**
- **Player Blocking:** Prevent interaction with problematic users
- **Credit Score:** Behavioral accountability system
- **Host Controls:** Event organizers can manage participants
- **Rating Disputes:** System for challenging unfair ratings

### **Data Protection**
- **API Authentication:** Sanctum token-based security
- **Role-based Permissions:** Player/Host/Admin access levels
- **Location Privacy:** Optional location sharing
- **Personal Data:** Secure profile information handling

## 📈 **Monetization Strategy**

### **Premium Subscription**
- **Queue Priority:** Skip waiting lists
- **Advanced Filtering:** More search options
- **Event History:** Extended match records
- **Premium Events:** Exclusive tournament access
- **Enhanced Support:** Priority customer service

### **Event Fees**
- **Tournament Entry:** Paid competitive events
- **Coaching Sessions:** Professional training fees
- **Venue Partnerships:** Commission from venue bookings

## 🎯 **Success Metrics**

### **User Engagement**
- **Monthly Active Users:** Target 10K+ MAU
- **Event Participation Rate:** 70%+ participation rate
- **Community Growth:** 50+ active communities
- **Rating Completion:** 80%+ post-match rating rate

### **Platform Health**
- **Credit Score Distribution:** Maintain healthy score distribution
- **No-show Rate:** <10% no-show rate
- **User Retention:** 60%+ monthly retention
- **Community Activity:** Average 5+ events per community per month

## 🚀 **Roadmap**

### **Phase 3: API Development (Current)**
- Complete EventController implementation
- UserController for profile management  
- CommunityController for community features
- NotificationController for in-app notifications

### **Phase 4: Advanced Features**
- Match history and detailed statistics
- Real-time features with Pusher
- WhatsApp integration via Fonnte
- Payment system integration

### **Phase 5: PWA Frontend**
- Progressive Web App implementation
- Offline capabilities
- QR code scanning
- Push notification setup

### **Phase 6: Scale & Optimize**
- Performance optimization
- Advanced analytics
- Additional sports support
- Enterprise features

---

**🏗️ Current Status:** Database ✅ | API Controllers 🚧 | Frontend ⏳ | Testing ⏳