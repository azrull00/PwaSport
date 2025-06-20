# RacketHub - User Pages & Features Breakdown

## 1. PLAYER PAGES & FEATURES

### 1.1 Onboarding & Authentication
**Landing/Onboarding Page**
- App introduction and value proposition
- Feature highlights with screenshots/animations
- "Get Started" CTA button
- App download/install prompt

**Registration Page**
- Choose verification method (Email or WhatsApp)
- Basic registration form (name, email/phone)
- Terms & conditions acceptance
- Privacy policy agreement

**Verification Pages**
- Email verification with code input
- WhatsApp OTP verification (Fonnte integration)
- Resend verification option
- Alternative verification method option

**Profile Setup (Mandatory)**
- Personal information (name, photo, bio)
- Location setup with GPS/manual input
- Preferred sports selection
- Skill level self-assessment
- Preferred areas setup (max 3 for free users)
- Profile completion progress indicator

### 1.2 Main App Pages

**Dashboard/Home Page**
- Welcome message with user name
- Quick stats (events joined, communities, rating)
- Nearby events recommendations
- Recently joined communities
- Quick access buttons (Search Events, My Events, Profile)
- Notification center access

**Event Discovery Page**
- Search bar with filters
- Map view with event markers
- List view with event cards
- Filters: Sport, Distance, Skill Level, Date, Time
- Sort options: Distance, Date, Skill Match
- Real-time availability updates
- Save/favorite events option

**Event Details Page**
- Event information (sport, date, time, location)
- Community details and rating
- Host information and contact
- Participant list (limited info pre-check-in)
- Venue information and directions
- Join/Leave event buttons
- Share event option

**My Events Page**
- Upcoming events tab
- Past events tab
- Event status tracking
- QR code for check-in
- Event details and location
- Cancel event option (with credit score penalty warning)
- **Player rating interface** (after match completion)

**Communities Page**
- Joined communities list
- Community search and discovery
- Community details and ratings
- Join/leave community options
- Community event history

**Community Detail Page**
- Community information and description
- Host details and contact
- Upcoming events from this community
- Member count and activity
- Community rating and reviews
- Join community chat (post-event participation)

**Chat/Messages Page**
- Community chat rooms list
- Real-time messaging interface
- Message history
- Member list (post-check-in visibility)
- Notification settings per chat

**Profile Page**
- Personal information display
- QR code for check-in
- Sports ratings and MMR per sport
- **Credit Score display** (starts at 100)
- Match history (last 3 visible to others)
- Player ratings received from others
- Achievement badges

**Match History Page**
- Complete personal match history across all sports
- Filter by sport, date range, opponent, result
- Detailed match information (opponent, score, MMR changes)
- Performance statistics and trends
- Win/loss ratios per sport
- MMR progression charts
- Export match data option

**Settings Page**
- Account settings
- Notification preferences (Email/WhatsApp)
- Preferred areas management
- Logout option

**Player Rating Page**
- Rate players after match completion
- View ratings received from other players
- Rating history and feedback
- Rating criteria (skill, sportsmanship, punctuality)
- Dispute rating system

### 1.3 Matchmaking & Privacy Features
**Matchmaking Room**
- Limited opponent info (rating, MMR, last 3 matches)
- Queue position display
- Estimated wait time
- Queue protection status (Premium only)
- Appeal system access for switches

**Post-Check-in Views**
- Full participant profiles
- Contact information access
- Enhanced community features
- Full chat participation

---

## 2. HOST PAGES & FEATURES

### 2.1 Host-Specific Pages (Additional to Player Pages)

**Host Dashboard**
- Community management overview
- Upcoming events summary
- Player management tools
- Revenue/analytics summary (if applicable)
- Quick actions (Create Event, Manage Community)

**Community Management Page**
- Create new community
- Edit community information
- Manage co-hosts and permissions
- Community performance metrics
- Member management
- Community settings

**Event Creation Page**
- Event details form (sport, date, time, venue)
- Event type selection (Mabar, Coaching, Friendly Match, Tournament)
- Participant limits and court configuration
- Registration settings
- Auto-queue configuration
- Premium event settings (host-set pricing for future)
- Publish/draft options

**Event Management Page**
- Active events list
- Participant management
- Queue management with override options
- Player switching interface (free players only)
- Check-in management
- **Match Score Input System** (after match completion)
- Event status controls
- Court assignment management

**Player Management Interface**
- Participant list with details
- Player credit score display
- Player switching functionality
- Reason requirement for switches
- Premium player protection indicators
- No-show reporting tools
- Player communication tools

**Match Score Input Page**
- Input match results and scores
- Player performance evaluation
- MMR/ELO calculation trigger
- Match completion confirmation
- Player rating prompt after score input

**Venue Management Page**
- Add/edit venue information
- Court configuration per venue
- Venue photos and details
- Contact information
- Availability calendar

**Host Analytics Page**
- Event performance metrics
- Community growth statistics
- Player retention rates
- Rating trends
- Revenue analytics (future)

**Community Match History Page**
- Complete match history for all community events
- Filter by event, sport, date range, participants
- Match results and outcomes tracking
- Community performance statistics
- Player performance within community
- Event success rates and analysis
- Export community match data

### 2.2 Host-Specific Features
- **Player Override System**: Switch free players with mandatory reasoning
- **Premium Protection**: Cannot switch premium players
- **Check-in Management**: QR code scanning and manual check-in
- **Match Score Input**: Input match results for MMR/ELO calculation
- **Event Type Management**: Create different event types (Mabar, Coaching, Friendly, Tournament)
- **Community Rating**: View detailed community feedback
- **Multi-Host Management**: Add/remove co-hosts
- **Event Templates**: Save and reuse event configurations
- **Community Match Tracking**: Complete access to all community match history
- **Performance Analytics**: Detailed analysis of community and player performance
- **Credit Score Monitoring**: View player credit scores during event management

---

## 3. ADMIN PAGES & FEATURES

### 3.1 Admin Dashboard & Management

**Admin Dashboard**
- Platform overview metrics
- Real-time system status
- Recent reports and alerts
- Quick access to management tools
- System health indicators

**User Management Page**
- User search and filtering
- User account details and history
- Subscription management
- User suspension/restoration tools
- User activity monitoring

**Community Management Page**
- Community approval/rejection
- Community performance monitoring
- Host verification tools
- Community rating oversight
- Community violation reports

**Event Management Page**
- Event monitoring and oversight
- Event approval system (if needed)
- Problematic event flagging
- Event analytics and reporting

**Reports & Disputes Page**
- Player reports management
- Dispute resolution tools
- Appeal processing system
- Evidence review interface
- Resolution tracking

**Credit Score Management Page**
- Monitor player credit scores across platform
- Credit score penalty configuration
- Credit score restoration tools
- Credit score analytics and trends
- Manual credit score adjustments

**Penalty System Page**
- Penalty rule configuration
- Automated penalty monitoring
- Manual penalty assignment
- Appeal review system
- Penalty analytics

**System Configuration Page**
- App settings and parameters
- Notification templates
- System maintenance tools
- Feature toggles
- Performance monitoring

**Platform Match History Page**
- Complete match history across all communities and events
- Advanced filtering (sport, community, date, players, results)
- Platform-wide match statistics and analytics
- Suspicious activity detection (potential smurf accounts)
- Match data integrity monitoring
- Bulk data export and reporting tools
- Historical performance analysis

### 3.2 Admin-Specific Features
- **User Account Control**: Suspend, restore, or modify user accounts
- **Credit Score Management**: Monitor and manage platform-wide credit scores
- **Report Resolution**: Handle player disputes and violations
- **System Analytics**: Comprehensive platform performance metrics
- **Content Moderation**: Monitor and moderate community content
- **Anti-Smurf Enforcement**: Skill verification and monitoring tools
- **Complete Match History Access**: View all matches across the entire platform
- **Match Data Analysis**: Detect patterns, anomalies, and potential issues
- **Event Type Analytics**: Monitor performance across different event types
- **Player Rating Oversight**: Monitor player-to-player rating system integrity

---

## 4. SHARED FEATURES ACROSS ALL ROLES

### 4.1 Core Navigation
- Bottom navigation bar (mobile-first)
- Side menu with role-specific options
- Search functionality
- Notification center
- Quick action buttons

### 4.2 Notification System
- Email notifications via SMTP
- WhatsApp notifications via Fonnte
- In-app notification center
- Notification preferences management
- Real-time notification delivery

### 4.3 Security & Privacy
- Two-factor authentication options
- Data export/deletion tools
- Security activity logs
- Account recovery options

### 4.4 Progressive Web App Features
- Offline functionality
- App installation prompt
- Background sync

---

## 5. TECHNICAL IMPLEMENTATION NOTES

### 5.1 Responsive Design
- Mobile-first approach
- Tablet optimization
- Desktop compatibility
- Touch-friendly interfaces
- Accessibility compliance

### 5.2 Performance Considerations
- Lazy loading for images and components
- Caching strategies
- Optimized database queries
- CDN integration
- Progressive loading

### 5.3 Integration Points
- Fonnte WhatsApp API
- SMTP email service
- Google Maps API
- Laravel WebSockets/Pusher

### 5.4 New Core Systems
- **Credit Score Algorithm**: Starting at 100, decreases on event cancellation
- **Event Type System**: Mabar, Coaching, Friendly Match, Tournament
- **Player Rating System**: Skill, sportsmanship, punctuality ratings
- **Match Score Input**: Host-driven score input for MMR/ELO calculation
- **Premium Event Framework**: Host-set pricing (future implementation)

### 5.5 Credit Score Algorithm Details
**Initial Score**: 100 points per player

**Penalty System**:
- Cancel event 24+ hours before: -5 points
- Cancel event 12-24 hours before: -10 points
- Cancel event 6-12 hours before: -15 points
- Cancel event 2-6 hours before: -20 points
- Cancel event < 2 hours before: -25 points
- No-show (host reported): -30 points

**Score Recovery**:
- Complete event without issues: +2 points (max 100)
- Host rating 4+ stars: +1 bonus point
- Consecutive 5 events completed: +5 bonus points

**Score Restrictions**:
- Score 80-100: No restrictions
- Score 60-79: Cannot join premium events (future)
- Score 40-59: Limited to 2 events per week
- Score 20-39: Limited to 1 event per week
- Score 0-19: Temporary ban for 7 days

This comprehensive breakdown provides the foundation for detailed UI/UX design and development planning for each user role in the RacketHub application. 