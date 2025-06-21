import React, { useState, useEffect, useRef } from 'react';

const ChatPage = ({ user, userToken, onNavigate }) => {
    const [activeView, setActiveView] = useState('communities'); // communities, chat, communityInfo, userProfile, privateChats, privateChat, notifications
    const [communities, setCommunities] = useState([]);
    const [selectedCommunity, setSelectedCommunity] = useState(null);
    const [communityDetails, setCommunityDetails] = useState(null);
    const [communityMembers, setCommunityMembers] = useState([]);
    const [selectedUser, setSelectedUser] = useState(null);
    const [userProfile, setUserProfile] = useState(null);
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    
    // Private messaging states
    const [conversations, setConversations] = useState([]);
    const [selectedConversation, setSelectedConversation] = useState(null);
    const [privateMessages, setPrivateMessages] = useState([]);
    const [newPrivateMessage, setNewPrivateMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isLoadingMessages, setIsLoadingMessages] = useState(false);
    const [isLoadingCommunityDetails, setIsLoadingCommunityDetails] = useState(false);
    const [isLoadingUserProfile, setIsLoadingUserProfile] = useState(false);
    const [isSending, setIsSending] = useState(false);
    const [error, setError] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [showCommunityInfo, setShowCommunityInfo] = useState(false);
    
    // Notification states
    const [notifications, setNotifications] = useState([]);
    const [isLoadingNotifications, setIsLoadingNotifications] = useState(false);
    const [notificationStats, setNotificationStats] = useState({ unread: 0, total: 0 });
    
    // Ref for auto-scrolling chat messages
    const messagesEndRef = useRef(null);
    const chatContainerRef = useRef(null);
    const [isNearBottom, setIsNearBottom] = useState(true);
    const lastScrollHeight = useRef(0);
    const shouldAutoScroll = useRef(true);

    useEffect(() => {
        if (activeView === 'communities') {
        loadMyCommunities();
        } else if (activeView === 'privateChats') {
            loadConversations();
        } else if (activeView === 'notifications') {
            loadNotifications();
        }

        // Listen for start private chat event
        const handleStartPrivateChat = (event) => {
            const { userId } = event.detail;
            startPrivateChat(userId);
        };

        window.addEventListener('startPrivateChat', handleStartPrivateChat);

        return () => {
            window.removeEventListener('startPrivateChat', handleStartPrivateChat);
        };
    }, [activeView]);

    // Auto-scroll to bottom when messages change, but only if user is near bottom
    useEffect(() => {
        if (shouldAutoScroll.current) {
            scrollToBottom();
        }
    }, [messages, privateMessages]);

    // Auto-scroll to bottom when component mounts or selectedCommunity changes
    useEffect(() => {
        if (selectedCommunity && messages.length > 0) {
            setIsNearBottom(true);
            scrollToBottom();
        }
    }, [selectedCommunity]);

    // Check if user is near bottom of chat
    const checkIfNearBottom = () => {
        if (chatContainerRef.current) {
            const container = chatContainerRef.current;
            const threshold = 100; // pixels from bottom
            const isNear = container.scrollHeight - container.scrollTop - container.clientHeight < threshold;
            setIsNearBottom(isNear);
        }
    };

    const scrollToBottom = () => {
        // Use multiple methods to ensure scrolling works across different scenarios
        if (messagesEndRef.current) {
            messagesEndRef.current.scrollIntoView({ 
                behavior: 'smooth',
                block: 'end'
            });
        }
        
        // Fallback method
        if (chatContainerRef.current) {
            const container = chatContainerRef.current;
            container.scrollTop = container.scrollHeight;
        }
    };

    // Force scroll to bottom (instant, for when sending messages)
    const forceScrollToBottom = () => {
        setIsNearBottom(true);
        if (messagesEndRef.current) {
            messagesEndRef.current.scrollIntoView({ 
                behavior: 'auto',
                block: 'end'
            });
        }
        
        if (chatContainerRef.current) {
            const container = chatContainerRef.current;
            container.scrollTop = container.scrollHeight;
        }
    };

    const loadMyCommunities = async () => {
        setIsLoading(true);
        setError('');

        try {
            const response = await fetch('/api/communities/my-communities', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setCommunities(data.data.communities || []);
            } else {
                setError(data.message || 'Gagal memuat komunitas');
            }
        } catch (error) {
            console.error('Error loading my communities:', error);
            setError('Terjadi kesalahan saat memuat komunitas');
        } finally {
            setIsLoading(false);
        }
    };

    const loadCommunityMessages = async (communityId) => {
        setIsLoadingMessages(true);
        setError('');

        try {
            const response = await fetch(`/api/communities/${communityId}/messages?per_page=50`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setMessages(data.data.messages || []);
                // Scroll to bottom after loading messages
                setTimeout(() => {
                    forceScrollToBottom();
                }, 100);
            } else {
                setError(data.message || 'Gagal memuat pesan');
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            setError('Terjadi kesalahan saat memuat pesan');
        } finally {
            setIsLoadingMessages(false);
        }
    };

    const loadCommunityDetails = async (communityId) => {
        setIsLoadingCommunityDetails(true);
        setError('');

        try {
            const response = await fetch(`/api/communities/${communityId}`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setCommunityDetails(data.data.community);
            } else {
                setError(data.message || 'Gagal memuat detail komunitas');
            }
        } catch (error) {
            console.error('Error loading community details:', error);
            setError('Terjadi kesalahan saat memuat detail komunitas');
        } finally {
            setIsLoadingCommunityDetails(false);
        }
    };

    const loadCommunityMembers = async (communityId) => {
        try {
            const response = await fetch(`/api/communities/${communityId}/members`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setCommunityMembers(data.data.members || []);
            }
        } catch (error) {
            console.error('Error loading community members:', error);
        }
    };

    const loadUserProfile = async (userId) => {
        setIsLoadingUserProfile(true);
        setError('');

        try {
            const response = await fetch(`/api/users/${userId}/profile`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setUserProfile(data.data.user);
                setActiveView('userProfile');
            } else {
                setError(data.message || 'Gagal memuat profil user');
            }
        } catch (error) {
            console.error('Error loading user profile:', error);
            setError('Terjadi kesalahan saat memuat profil user');
        } finally {
            setIsLoadingUserProfile(false);
        }
    };

    // Private messaging functions
    const loadConversations = async () => {
        setIsLoading(true);
        setError('');

        try {
            const response = await fetch('/api/messages/conversations', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setConversations(data.data.conversations || []);
            } else {
                setError(data.message || 'Gagal memuat percakapan');
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
            setError('Terjadi kesalahan saat memuat percakapan');
        } finally {
            setIsLoading(false);
        }
    };

    const loadPrivateMessages = async (userId) => {
        setIsLoadingMessages(true);
        setError('');

        try {
            const response = await fetch(`/api/messages/conversations/${userId}`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setPrivateMessages(data.data.messages || []);
                setTimeout(() => {
                    forceScrollToBottom();
                }, 100);
            } else {
                setError(data.message || 'Gagal memuat pesan');
            }
        } catch (error) {
            console.error('Error loading private messages:', error);
            setError('Terjadi kesalahan saat memuat pesan');
        } finally {
            setIsLoadingMessages(false);
        }
    };

    const sendPrivateMessage = async () => {
        if (!newPrivateMessage.trim() || !selectedConversation) return;

        setIsSending(true);
        const messageText = newPrivateMessage.trim();
        
        setNewPrivateMessage('');
        forceScrollToBottom();

        try {
            const response = await fetch('/api/messages/send', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    receiver_id: selectedConversation.user.id,
                    message: messageText
                })
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setPrivateMessages(prev => [...prev, data.data.message]);
                setTimeout(() => {
                    forceScrollToBottom();
                }, 50);
            } else {
                setError(data.message || 'Gagal mengirim pesan');
                setNewPrivateMessage(messageText);
            }
        } catch (error) {
            console.error('Error sending private message:', error);
            setError('Terjadi kesalahan saat mengirim pesan');
            setNewPrivateMessage(messageText);
        } finally {
            setIsSending(false);
        }
    };

    const startPrivateChat = async (userId) => {
        try {
            // Find existing conversation or create new one
            let conversation = conversations.find(conv => conv.user.id === userId);
            
            if (!conversation) {
                // Load user details for new conversation
                const response = await fetch(`/api/users/${userId}/profile`, {
                    headers: {
                        'Authorization': `Bearer ${userToken}`,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                
                if (data.status === 'success') {
                    conversation = {
                        user: {
                            id: data.data.user.id,
                            name: data.data.user.name,
                            email: data.data.user.email,
                            profile: data.data.user.profile,
                            subscription_tier: data.data.user.subscription_tier,
                        },
                        last_message: null,
                        unread_count: 0,
                        last_activity: new Date().toISOString(),
                    };
                }
            }

            if (conversation) {
                setSelectedConversation(conversation);
                setActiveView('privateChat');
                setPrivateMessages([]);
                loadPrivateMessages(userId);
            }
        } catch (error) {
            console.error('Error starting private chat:', error);
            setError('Terjadi kesalahan saat memulai percakapan');
        }
    };

    const handleSelectCommunity = (community) => {
        setSelectedCommunity(community);
        setActiveView('chat');
        setMessages([]); // Clear previous messages
        setCommunityDetails(null);
        setCommunityMembers([]);
        loadCommunityMessages(community.id);
        loadCommunityDetails(community.id);
        loadCommunityMembers(community.id);
    };

    const handleUserClick = (userId) => {
        if (userId === user.id) return; // Don't load own profile from chat
        setSelectedUser(userId);
        loadUserProfile(userId);
    };

    const handleShowCommunityInfo = () => {
        setActiveView('communityInfo');
    };

    const handleBackToChat = () => {
        setActiveView('chat');
    };

    const handleBackToCommunities = () => {
        setActiveView('communities');
        setSelectedCommunity(null);
        setCommunityDetails(null);
        setCommunityMembers([]);
        setMessages([]);
    };

    const handleBackToPrivateChats = () => {
        setActiveView('privateChats');
        setSelectedConversation(null);
        setPrivateMessages([]);
    };

    const handleShowPrivateChats = () => {
        setActiveView('privateChats');
        loadConversations();
    };

    const handleSelectConversation = (conversation) => {
        setSelectedConversation(conversation);
        setActiveView('privateChat');
        setPrivateMessages([]);
        loadPrivateMessages(conversation.user.id);
    };

    const handleSendMessage = async () => {
        if (!newMessage.trim() || !selectedCommunity) return;

        setIsSending(true);
        const messageText = newMessage.trim();
        
        // Clear input immediately for better UX
        setNewMessage('');
        
        // Force scroll to bottom immediately when user sends message
        forceScrollToBottom();

        try {
            const response = await fetch(`/api/communities/${selectedCommunity.id}/messages`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: messageText
                })
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                // Add the new message to the list
                setMessages(prev => [...prev, data.data.message]);
                // Force scroll to bottom after adding message
                setTimeout(() => {
                    forceScrollToBottom();
                }, 50);
            } else {
                setError(data.message || 'Gagal mengirim pesan');
                setNewMessage(messageText); // Restore message on error
            }
        } catch (error) {
            console.error('Error sending message:', error);
            setError('Terjadi kesalahan saat mengirim pesan');
            setNewMessage(messageText); // Restore message on error
        } finally {
            setIsSending(false);
        }
    };

    const handleKeyPress = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage();
        }
    };

    // Handle input change with auto-scroll
    const handleMessageChange = (e) => {
        setNewMessage(e.target.value);
        // Auto-scroll to bottom when user starts typing (they want to send a message)
        if (e.target.value.length > 0) {
            setIsNearBottom(true);
            setTimeout(() => {
                scrollToBottom();
            }, 10);
        }
    };

    // Handle focus on input - scroll to bottom
    const handleInputFocus = () => {
        setIsNearBottom(true);
        setTimeout(() => {
            scrollToBottom();
        }, 100);
    };

    const formatTime = (timestamp) => {
        const date = new Date(timestamp);
        const now = new Date();
        const diffInHours = (now - date) / (1000 * 60 * 60);
        
        if (diffInHours < 1) {
            const diffInMinutes = Math.floor((now - date) / (1000 * 60));
            return diffInMinutes < 1 ? 'Baru saja' : `${diffInMinutes} menit lalu`;
        } else if (diffInHours < 24) {
            return `${Math.floor(diffInHours)} jam lalu`;
        } else {
            return date.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    };

    const filteredCommunities = communities.filter(community =>
        community.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        community.sport?.name.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const CommunitiesView = () => (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3">
                <div className="flex items-center justify-between mb-3">
                    <h1 className="text-lg font-semibold text-gray-900">Chat</h1>
                </div>
                
                {/* Navigation Tabs */}
                <div className="flex space-x-1 bg-gray-100 rounded-lg p-1">
                    <button 
                        onClick={() => setActiveView('communities')}
                        className={`flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors ${
                            activeView === 'communities' 
                                ? 'bg-white text-primary shadow-sm' 
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Komunitas
                    </button>
                    <button 
                        onClick={() => setActiveView('privateChats')}
                        className={`flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors ${
                            activeView === 'privateChats' 
                                ? 'bg-white text-primary shadow-sm' 
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Chat Pribadi
                    </button>
                    <button 
                        onClick={() => setActiveView('notifications')}
                        className={`flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors relative ${
                            activeView === 'notifications' 
                                ? 'bg-white text-primary shadow-sm' 
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Notifikasi
                        {notificationStats.unread > 0 && (
                            <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                {notificationStats.unread > 9 ? '9+' : notificationStats.unread}
                            </span>
                        )}
                    </button>
                </div>
            </div>

            {/* Search */}
            <div className="p-4">
                <div className="relative">
                    <input
                        type="text"
                        placeholder="Cari komunitas..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent"
                    />
                    <div className="absolute left-3 top-1/2 transform -translate-y-1/2">
                        <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            {/* Error Message */}
            {error && (
                <div className="mx-4 mb-4 bg-red-50 border border-red-200 rounded-xl p-4">
                    <p className="text-red-600 text-sm">{error}</p>
                </div>
            )}

            {/* Communities List */}
            <div className="px-4 pb-20">
                {isLoading ? (
                    <div className="flex justify-center py-8">
                        <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    </div>
                ) : filteredCommunities.length > 0 ? (
                    <div className="space-y-3">
                        {filteredCommunities.map((community) => (
                            <div
                                key={community.id}
                                onClick={() => handleSelectCommunity(community)}
                                className="bg-white rounded-xl p-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                            >
                                <div className="flex items-center space-x-3">
                                    <div className="w-12 h-12 bg-primary rounded-full flex items-center justify-center">
                                        {community.icon_url ? (
                                            <img
                                                src={`/storage/${community.icon_url}`}
                                                alt={community.name}
                                                className="w-8 h-8 rounded-full object-cover"
                                            />
                                        ) : (
                                            <span className="text-white font-bold text-lg">
                                                {community.name.charAt(0)}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <h3 className="font-semibold text-gray-900 truncate">
                                            {community.name}
                                        </h3>
                                        <p className="text-sm text-gray-600 truncate">
                                            {community.sport?.name} • {community.member_count || 0} anggota
                                        </p>
                                        {community.latest_message && (
                                            <p className="text-sm text-gray-500 truncate mt-1">
                                                {community.latest_message.user?.name}: {community.latest_message.message}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-col items-end space-y-1">
                                        {community.latest_message && (
                                            <span className="text-xs text-gray-400">
                                                {formatTime(community.latest_message.created_at)}
                                            </span>
                                        )}
                                        {community.unread_count > 0 && (
                                            <div className="bg-primary text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                                {community.unread_count > 99 ? '99+' : community.unread_count}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-12">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8V4l4 4z" />
                            </svg>
                        </div>
                        <h3 className="font-semibold text-gray-900 mb-2">Belum Ada Komunitas</h3>
                        <p className="text-gray-600 text-sm">
                            {searchQuery ? 'Tidak ada komunitas yang sesuai dengan pencarian' : 'Bergabunglah dengan komunitas untuk mulai chat'}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );

    const ChatView = () => (
        <div className="bg-white min-h-screen flex flex-col">
            {/* Chat Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3 flex items-center space-x-3">
                <button
                    onClick={handleBackToCommunities}
                    className="p-1 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <svg className="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div className="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                    {selectedCommunity?.icon_url ? (
                        <img
                            src={`/storage/${selectedCommunity.icon_url}`}
                            alt={selectedCommunity.name}
                            className="w-8 h-8 rounded-full object-cover"
                        />
                    ) : (
                        <span className="text-white font-bold">
                            {selectedCommunity?.name.charAt(0)}
                        </span>
                    )}
                </div>
                <div className="flex-1">
                    <h1 className="font-semibold text-gray-900">{selectedCommunity?.name}</h1>
                    <p className="text-sm text-gray-600">
                        {communityMembers.length || selectedCommunity?.members_count || 0} anggota
                    </p>
                </div>
                <button
                    onClick={handleShowCommunityInfo}
                    className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>
            </div>

            {/* Error Message */}
            {error && (
                <div className="mx-4 mt-4 bg-red-50 border border-red-200 rounded-xl p-4">
                    <p className="text-red-600 text-sm">{error}</p>
                </div>
            )}

            {/* Messages */}
            <div 
                id="chat-messages"
                className="flex-1 overflow-y-auto p-4 space-y-4 chat-container smooth-scroll chat-auto-scroll"
                style={{ maxHeight: 'calc(100vh - 140px)' }}
                ref={chatContainerRef}
                onScroll={checkIfNearBottom}
            >
                {isLoadingMessages ? (
                    <div className="flex justify-center py-8">
                        <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    </div>
                ) : messages.length > 0 ? (
                    messages.map((message) => (
                        <div
                            key={message.id}
                            className={`flex ${message.user_id === user.id ? 'justify-end' : 'justify-start'}`}
                        >
                            <div className={`max-w-xs lg:max-w-md ${
                                message.user_id === user.id 
                                    ? 'bg-primary text-white' 
                                    : 'bg-gray-100 text-gray-900'
                            } rounded-2xl px-4 py-2`}>
                                {message.user_id !== user.id && (
                                    <button
                                        onClick={() => handleUserClick(message.user_id)}
                                        className={`text-xs font-medium mb-1 opacity-70 hover:opacity-100 transition-opacity ${
                                            message.user_id === user.id ? 'text-blue-100' : 'text-blue-600'
                                        }`}
                                    >
                                        {message.user?.name}
                                    </button>
                                )}
                                <p className="text-sm">{message.message}</p>
                                <p className={`text-xs mt-1 ${
                                    message.user_id === user.id ? 'text-blue-100' : 'text-gray-500'
                                }`}>
                                    {formatTime(message.created_at)}
                                </p>
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="text-center py-12">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h3 className="font-semibold text-gray-900 mb-2">Belum Ada Pesan</h3>
                        <p className="text-gray-600 text-sm">Mulai percakapan dengan mengirim pesan pertama</p>
                    </div>
                )}
                
                {/* Invisible element to scroll to */}
                <div ref={messagesEndRef} className="h-1 chat-message-end" />
            </div>

            {/* Message Input */}
            <div className="chat-input-container">
                <div className="flex items-center space-x-2">
                    <input
                        type="text"
                        placeholder="Ketik pesan..."
                        value={newMessage}
                        onChange={handleMessageChange}
                        onKeyPress={handleKeyPress}
                        onFocus={handleInputFocus}
                        disabled={isSending}
                        className="flex-1 px-4 py-2 border border-gray-200 rounded-full focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-50 chat-input"
                    />
                    <button
                        onClick={handleSendMessage}
                        disabled={!newMessage.trim() || isSending}
                        className="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center hover:bg-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {isSending ? (
                            <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        ) : (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );

    const CommunityInfoView = () => (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3 flex items-center space-x-3">
                <button
                    onClick={handleBackToChat}
                    className="p-1 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <svg className="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h1 className="text-lg font-semibold text-gray-900">Info Komunitas</h1>
            </div>

            {isLoadingCommunityDetails ? (
                <div className="flex justify-center py-8">
                    <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                </div>
            ) : communityDetails ? (
                <div className="p-4 space-y-4">
                    {/* Community Header */}
                    <div className="bg-white rounded-xl p-6 shadow-sm">
                        <div className="flex items-center space-x-4 mb-4">
                            <div className="w-16 h-16 bg-primary rounded-full flex items-center justify-center">
                                {communityDetails.icon_url ? (
                                    <img
                                        src={`/storage/${communityDetails.icon_url}`}
                                        alt={communityDetails.name}
                                        className="w-12 h-12 rounded-full object-cover"
                                    />
                                ) : (
                                    <span className="text-white font-bold text-xl">
                                        {communityDetails.name.charAt(0)}
                                    </span>
                                )}
                            </div>
                            <div className="flex-1">
                                <h2 className="text-xl font-bold text-gray-900">{communityDetails.name}</h2>
                                <p className="text-gray-600">{communityDetails.sport?.name}</p>
                                <div className="flex items-center space-x-4 mt-2">
                                    <span className="text-sm text-gray-500">
                                        👥 {communityMembers.length} anggota
                                    </span>
                                    <span className="text-sm text-gray-500">
                                        📍 {communityDetails.venue_city || communityDetails.city}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        {communityDetails.description && (
                            <div className="mb-4">
                                <h3 className="font-semibold text-gray-900 mb-2">Deskripsi</h3>
                                <p className="text-gray-600 text-sm leading-relaxed">{communityDetails.description}</p>
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="text-gray-500">Tipe:</span>
                                <span className="ml-2 font-medium">
                                    {communityDetails.community_type === 'public' ? 'Publik' : 
                                     communityDetails.community_type === 'private' ? 'Privat' : 'Undangan'}
                                </span>
                            </div>
                            <div>
                                <span className="text-gray-500">Level:</span>
                                <span className="ml-2 font-medium">{communityDetails.skill_level_focus || 'Mixed'}</span>
                            </div>
                            {communityDetails.membership_fee && (
                                <div>
                                    <span className="text-gray-500">Biaya:</span>
                                    <span className="ml-2 font-medium">Rp {parseInt(communityDetails.membership_fee).toLocaleString()}/bulan</span>
                                </div>
                            )}
                            {communityDetails.average_skill_rating && (
                                <div>
                                    <span className="text-gray-500">Rating:</span>
                                    <span className="ml-2 font-medium">{parseFloat(communityDetails.average_skill_rating).toFixed(1)} ⭐</span>
                                </div>
                            )}
                        </div>

                        <button
                            onClick={() => onNavigate && onNavigate('communityDetail', { communityId: communityDetails.id })}
                            className="w-full mt-4 bg-primary text-white py-2 rounded-lg font-medium hover:bg-primary-dark transition-colors"
                        >
                            Lihat Detail Lengkap
                        </button>
                    </div>

                    {/* Community Members */}
                    <div className="bg-white rounded-xl p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900 mb-4">Anggota ({communityMembers.length})</h3>
                        
                        {communityMembers.length > 0 ? (
                            <div className="space-y-3">
                                {communityMembers.slice(0, 10).map((member) => (
                                    <div key={member.id} className="flex items-center justify-between">
                                        <div 
                                            className="flex items-center space-x-3 cursor-pointer hover:bg-gray-50 rounded-lg p-2 -m-2 transition-colors"
                                            onClick={() => handleUserClick(member.user?.id)}
                                        >
                                            <div className="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                                                <span className="text-white font-medium text-sm">
                                                    {member.user?.name?.charAt(0) || 'U'}
                                                </span>
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900">
                                                    {member.user?.name || 'User'}
                                                </p>
                                                <p className="text-sm text-gray-500">
                                                    {member.role === 'admin' ? 'Admin' : 
                                                     member.role === 'moderator' ? 'Moderator' : 'Anggota'}
                                                </p>
                                            </div>
                                        </div>
                                        
                                        {member.role === 'admin' && (
                                            <span className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">Admin</span>
                                        )}
                                        {member.role === 'moderator' && (
                                            <span className="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Moderator</span>
                                        )}
                                    </div>
                                ))}
                                
                                {communityMembers.length > 10 && (
                                    <p className="text-gray-500 text-sm text-center pt-2">
                                        Dan {communityMembers.length - 10} anggota lainnya
                                    </p>
                                )}
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <div className="text-4xl mb-2">👥</div>
                                <p className="text-gray-500">Belum ada data anggota</p>
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                <div className="text-center py-8">
                    <p className="text-gray-600">Gagal memuat detail komunitas</p>
                </div>
            )}
        </div>
    );

    const UserProfileView = () => (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3 flex items-center space-x-3">
                <button
                    onClick={handleBackToChat}
                    className="p-1 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <svg className="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h1 className="text-lg font-semibold text-gray-900">Profil Pengguna</h1>
            </div>

            {isLoadingUserProfile ? (
                <div className="flex justify-center py-8">
                    <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                </div>
            ) : userProfile ? (
                <div className="p-4 space-y-4">
                    {/* User Profile Header */}
                    <div className="bg-white rounded-xl p-6 shadow-sm">
                        <div className="flex items-center space-x-4 mb-4">
                            <div className="w-16 h-16 bg-primary rounded-full flex items-center justify-center">
                                {userProfile.profile?.profile_picture_url ? (
                                    <img
                                        src={userProfile.profile.profile_picture_url}
                                        alt={userProfile.name}
                                        className="w-12 h-12 rounded-full object-cover"
                                    />
                                ) : (
                                    <span className="text-white font-bold text-xl">
                                        {userProfile.name?.charAt(0) || 'U'}
                                    </span>
                                )}
                            </div>
                            <div className="flex-1">
                                <h2 className="text-xl font-bold text-gray-900">{userProfile.name}</h2>
                                {userProfile.subscription_tier === 'premium' && (
                                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        👑 Premium
                                    </span>
                                )}
                                <div className="flex items-center space-x-4 mt-2">
                                    {userProfile.profile?.location && (
                                        <span className="text-sm text-gray-500">
                                            📍 {userProfile.profile.location}
                                        </span>
                                    )}
                                    <span className="text-sm text-gray-500">
                                        📅 Bergabung {new Date(userProfile.created_at).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        {userProfile.profile?.bio && (
                            <div className="mb-4">
                                <h3 className="font-semibold text-gray-900 mb-2">Bio</h3>
                                <p className="text-gray-600 text-sm leading-relaxed">{userProfile.profile.bio}</p>
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-4 text-sm">
                            {userProfile.profile?.skill_level && (
                                <div>
                                    <span className="text-gray-500">Level:</span>
                                    <span className="ml-2 font-medium">{userProfile.profile.skill_level}</span>
                                </div>
                            )}
                            {userProfile.profile?.preferred_sports && (
                                <div>
                                    <span className="text-gray-500">Olahraga:</span>
                                    <span className="ml-2 font-medium">{userProfile.profile.preferred_sports}</span>
                                </div>
                            )}
                            {userProfile.profile?.experience_years && (
                                <div>
                                    <span className="text-gray-500">Pengalaman:</span>
                                    <span className="ml-2 font-medium">{userProfile.profile.experience_years} tahun</span>
                                </div>
                            )}
                            {userProfile.profile?.credit_score && (
                                <div>
                                    <span className="text-gray-500">Credit Score:</span>
                                    <span className="ml-2 font-medium">{userProfile.profile.credit_score}</span>
                                </div>
                            )}
                        </div>

                        <div className="flex space-x-3 mt-4">
                            <button
                                onClick={() => onNavigate && onNavigate('profile', { userId: userProfile.id })}
                                className="flex-1 bg-primary text-white py-2 rounded-lg font-medium hover:bg-primary-dark transition-colors"
                            >
                                Lihat Profil Lengkap
                            </button>
                            <button
                                onClick={() => startPrivateChat(userProfile.id)}
                                className="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-200 transition-colors"
                            >
                                Kirim Pesan
                            </button>
                        </div>
                    </div>

                    {/* User Stats */}
                    {userProfile.stats && (
                        <div className="bg-white rounded-xl p-6 shadow-sm">
                            <h3 className="font-semibold text-gray-900 mb-4">Statistik</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-primary">{userProfile.stats.events_joined || 0}</div>
                                    <div className="text-sm text-gray-600">Event Diikuti</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-primary">{userProfile.stats.matches_played || 0}</div>
                                    <div className="text-sm text-gray-600">Pertandingan</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-primary">{userProfile.stats.communities_joined || 0}</div>
                                    <div className="text-sm text-gray-600">Komunitas</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-primary">{userProfile.stats.win_rate || 0}%</div>
                                    <div className="text-sm text-gray-600">Win Rate</div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                <div className="text-center py-8">
                    <p className="text-gray-600">Gagal memuat profil pengguna</p>
                </div>
            )}
        </div>
    );

    const PrivateChatsView = () => (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3 flex items-center space-x-3">
                <button
                    onClick={handleBackToCommunities}
                    className="p-1 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <svg className="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h1 className="text-lg font-semibold text-gray-900">Pesan Pribadi</h1>
            </div>

            {/* Error Message */}
            {error && (
                <div className="mx-4 mt-4 bg-red-50 border border-red-200 rounded-xl p-4">
                    <p className="text-red-600 text-sm">{error}</p>
                </div>
            )}

            {/* Conversations List */}
            <div className="px-4 pb-20 mt-4">
                {isLoading ? (
                    <div className="flex justify-center py-8">
                        <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    </div>
                ) : conversations.length > 0 ? (
                    <div className="space-y-3">
                        {conversations.map((conversation) => (
                            <div
                                key={conversation.user.id}
                                onClick={() => handleSelectConversation(conversation)}
                                className="bg-white rounded-xl p-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                            >
                                <div className="flex items-center space-x-3">
                                    <div className="w-12 h-12 bg-primary rounded-full flex items-center justify-center">
                                        <span className="text-white font-bold text-lg">
                                            {conversation.user.name.charAt(0)}
                                        </span>
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center space-x-2">
                                            <h3 className="font-semibold text-gray-900 truncate">
                                                {conversation.user.name}
                                            </h3>
                                            {conversation.user.subscription_tier === 'premium' && (
                                                <span className="text-yellow-500 text-xs">👑</span>
                                            )}
                                        </div>
                                        {conversation.last_message && (
                                            <p className="text-sm text-gray-500 truncate mt-1">
                                                {conversation.last_message.is_from_me ? 'Anda: ' : ''}
                                                {conversation.last_message.message}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-col items-end space-y-1">
                                        {conversation.last_message && (
                                            <span className="text-xs text-gray-400">
                                                {formatTime(conversation.last_message.created_at)}
                                            </span>
                                        )}
                                        {conversation.unread_count > 0 && (
                                            <div className="bg-primary text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                                {conversation.unread_count > 99 ? '99+' : conversation.unread_count}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-12">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h3 className="font-semibold text-gray-900 mb-2">Belum Ada Percakapan</h3>
                        <p className="text-gray-600 text-sm">Mulai chat dengan teman dari halaman Teman</p>
                    </div>
                )}
            </div>
        </div>
    );

    const PrivateChatView = () => (
        <div className="bg-white min-h-screen flex flex-col">
            {/* Chat Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3 flex items-center space-x-3">
                <button
                    onClick={handleBackToPrivateChats}
                    className="p-1 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <svg className="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div className="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                    <span className="text-white font-bold">
                        {selectedConversation?.user.name.charAt(0)}
                    </span>
                </div>
                <div className="flex-1">
                    <div className="flex items-center space-x-2">
                        <h1 className="font-semibold text-gray-900">{selectedConversation?.user.name}</h1>
                        {selectedConversation?.user.subscription_tier === 'premium' && (
                            <span className="text-yellow-500 text-sm">👑</span>
                        )}
                    </div>
                    <p className="text-sm text-gray-600">Pesan Pribadi</p>
                </div>
                <button
                    onClick={() => handleUserClick(selectedConversation?.user.id)}
                    className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>
            </div>

            {/* Error Message */}
            {error && (
                <div className="mx-4 mt-4 bg-red-50 border border-red-200 rounded-xl p-4">
                    <p className="text-red-600 text-sm">{error}</p>
                </div>
            )}

            {/* Messages */}
            <div 
                className="flex-1 overflow-y-auto p-4 space-y-4"
                style={{ maxHeight: 'calc(100vh - 140px)' }}
                ref={chatContainerRef}
                onScroll={checkIfNearBottom}
            >
                {isLoadingMessages ? (
                    <div className="flex justify-center py-8">
                        <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    </div>
                ) : privateMessages.length > 0 ? (
                    privateMessages.map((message) => (
                        <div
                            key={message.id}
                            className={`flex ${message.sender_id === user.id ? 'justify-end' : 'justify-start'}`}
                        >
                            <div className={`max-w-xs lg:max-w-md ${
                                message.sender_id === user.id 
                                    ? 'bg-primary text-white' 
                                    : 'bg-gray-100 text-gray-900'
                            } rounded-2xl px-4 py-2`}>
                                <p className="text-sm">{message.message}</p>
                                <p className={`text-xs mt-1 ${
                                    message.sender_id === user.id ? 'text-blue-100' : 'text-gray-500'
                                }`}>
                                    {formatTime(message.created_at)}
                                    {message.sender_id === user.id && (
                                        <span className="ml-1">
                                            {message.read_at ? '✓✓' : '✓'}
                                        </span>
                                    )}
                                </p>
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="text-center py-12">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h3 className="font-semibold text-gray-900 mb-2">Mulai Percakapan</h3>
                        <p className="text-gray-600 text-sm">Kirim pesan pertama untuk memulai chat</p>
                    </div>
                )}
                
                <div ref={messagesEndRef} className="h-1" />
            </div>

            {/* Message Input */}
            <div className="p-4 border-t border-gray-100">
                <div className="flex items-center space-x-2">
                    <input
                        type="text"
                        placeholder="Ketik pesan..."
                        value={newPrivateMessage}
                        onChange={(e) => setNewPrivateMessage(e.target.value)}
                        onKeyPress={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                sendPrivateMessage();
                            }
                        }}
                        onFocus={handleInputFocus}
                        disabled={isSending}
                        className="flex-1 px-4 py-2 border border-gray-200 rounded-full focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-50"
                    />
                    <button
                        onClick={sendPrivateMessage}
                        disabled={!newPrivateMessage.trim() || isSending}
                        className="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center hover:bg-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {isSending ? (
                            <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        ) : (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );

    // Load notifications
    const loadNotifications = async () => {
        setIsLoadingNotifications(true);
        setError('');

        try {
            const response = await fetch('/api/notifications', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setNotifications(data.data.notifications || []);
                setNotificationStats({
                    unread: data.data.unread_count || 0,
                    total: data.data.total || 0
                });
            } else {
                setError(data.message || 'Gagal memuat notifikasi');
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            setError('Terjadi kesalahan saat memuat notifikasi');
        } finally {
            setIsLoadingNotifications(false);
        }
    };

    // Mark notification as read
    const markNotificationAsRead = async (notificationId) => {
        try {
            const response = await fetch(`/api/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                // Update local state
                setNotifications(prev => 
                    prev.map(notif => 
                        notif.id === notificationId 
                            ? { ...notif, read_at: new Date().toISOString() }
                            : notif
                    )
                );
                
                // Update stats
                setNotificationStats(prev => ({
                    ...prev,
                    unread: Math.max(0, prev.unread - 1)
                }));
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    };

    // Notifications View Component
    const NotificationsView = () => (
        <div className="bg-secondary min-h-screen">
            {/* Header - using same header as other views */}
            <div className="bg-white border-b border-gray-100 px-4 py-3">
                <div className="flex items-center justify-between mb-3">
                    <h1 className="text-lg font-semibold text-gray-900">Chat</h1>
                </div>
                
                {/* Navigation Tabs */}
                <div className="flex space-x-1 bg-gray-100 rounded-lg p-1">
                    <button 
                        onClick={() => setActiveView('communities')}
                        className={`flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors ${
                            activeView === 'communities' 
                                ? 'bg-white text-primary shadow-sm' 
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Komunitas
                    </button>
                    <button 
                        onClick={() => setActiveView('privateChats')}
                        className={`flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors ${
                            activeView === 'privateChats' 
                                ? 'bg-white text-primary shadow-sm' 
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Chat Pribadi
                    </button>
                    <button 
                        onClick={() => setActiveView('notifications')}
                        className={`flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors relative ${
                            activeView === 'notifications' 
                                ? 'bg-white text-primary shadow-sm' 
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Notifikasi
                        {notificationStats.unread > 0 && (
                            <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                {notificationStats.unread > 9 ? '9+' : notificationStats.unread}
                            </span>
                        )}
                    </button>
                </div>
            </div>

            {/* Error Message */}
            {error && (
                <div className="mx-4 mt-4 mb-4 bg-red-50 border border-red-200 rounded-xl p-4">
                    <p className="text-red-600 text-sm">{error}</p>
                </div>
            )}

            {/* Notifications List */}
            <div className="px-4 pb-20">
                {isLoadingNotifications ? (
                    <div className="flex justify-center py-8">
                        <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    </div>
                ) : notifications.length > 0 ? (
                    <div className="space-y-3">
                        {notifications.map((notification) => (
                            <div
                                key={notification.id}
                                onClick={() => markNotificationAsRead(notification.id)}
                                className={`bg-white rounded-xl p-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow ${
                                    !notification.read_at ? 'ring-2 ring-primary/20 bg-primary/5' : ''
                                }`}
                            >
                                <div className="flex items-start space-x-3">
                                    <div className={`w-2 h-2 rounded-full mt-2 ${
                                        !notification.read_at ? 'bg-primary' : 'bg-gray-300'
                                    }`} />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between mb-1">
                                            <h3 className={`font-semibold truncate ${
                                                !notification.read_at ? 'text-gray-900' : 'text-gray-700'
                                            }`}>
                                                {notification.title}
                                            </h3>
                                            <span className="text-xs text-gray-400 ml-2">
                                                {formatTime(notification.created_at)}
                                            </span>
                                        </div>
                                        <p className={`text-sm ${
                                            !notification.read_at ? 'text-gray-700' : 'text-gray-600'
                                        }`}>
                                            {notification.message}
                                        </p>
                                        {notification.type && (
                                            <div className="mt-2">
                                                <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                    notification.type === 'event' ? 'bg-blue-100 text-blue-700' :
                                                    notification.type === 'message' ? 'bg-green-100 text-green-700' :
                                                    notification.type === 'friend_request' ? 'bg-purple-100 text-purple-700' :
                                                    notification.type === 'match' ? 'bg-orange-100 text-orange-700' :
                                                    'bg-gray-100 text-gray-700'
                                                }`}>
                                                    {notification.type === 'event' ? '📅 Event' :
                                                     notification.type === 'message' ? '💬 Pesan' :
                                                     notification.type === 'friend_request' ? '👥 Pertemanan' :
                                                     notification.type === 'match' ? '🏸 Match' :
                                                     '📢 Info'}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-12">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-5 5v-5zM12 17H7a4 4 0 01-4-4V5a4 4 0 014-4h10a4 4 0 014 4v4" />
                            </svg>
                        </div>
                        <h3 className="font-semibold text-gray-900 mb-2">Belum Ada Notifikasi</h3>
                        <p className="text-gray-600 text-sm">Notifikasi akan muncul di sini ketika ada aktivitas baru</p>
                    </div>
                )}
            </div>
        </div>
    );

    return (
        <>
            {activeView === 'communities' && <CommunitiesView />}
            {activeView === 'chat' && <ChatView />}
            {activeView === 'communityInfo' && <CommunityInfoView />}
            {activeView === 'userProfile' && <UserProfileView />}
            {activeView === 'privateChats' && <PrivateChatsView />}
            {activeView === 'privateChat' && <PrivateChatView />}
            {activeView === 'notifications' && <NotificationsView />}
        </>
    );
};

export default ChatPage; 