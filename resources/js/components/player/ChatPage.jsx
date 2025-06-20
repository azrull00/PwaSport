import React, { useState, useEffect, useRef } from 'react';

const ChatPage = ({ user, userToken }) => {
    const [activeView, setActiveView] = useState('communities'); // communities, chat
    const [communities, setCommunities] = useState([]);
    const [selectedCommunity, setSelectedCommunity] = useState(null);
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isLoadingMessages, setIsLoadingMessages] = useState(false);
    const [isSending, setIsSending] = useState(false);
    const [error, setError] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    
    // Ref for auto-scrolling chat messages
    const messagesEndRef = useRef(null);
    const chatContainerRef = useRef(null);
    const [isNearBottom, setIsNearBottom] = useState(true);

    useEffect(() => {
        loadMyCommunities();
    }, []);

    // Auto-scroll to bottom when messages change, but only if user is near bottom
    useEffect(() => {
        if (isNearBottom) {
            scrollToBottom();
        }
    }, [messages, isNearBottom]);

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

    const handleSelectCommunity = (community) => {
        setSelectedCommunity(community);
        setActiveView('chat');
        setMessages([]); // Clear previous messages
        loadCommunityMessages(community.id);
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
                <h1 className="text-lg font-semibold text-gray-900">Chat Komunitas</h1>
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
                    onClick={() => setActiveView('communities')}
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
                        {selectedCommunity?.members_count || 0} anggota
                    </p>
                </div>
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
                                    <p className="text-xs font-medium mb-1 opacity-70">
                                        {message.user?.name}
                                    </p>
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

    return activeView === 'communities' ? <CommunitiesView /> : <ChatView />;
};

export default ChatPage; 