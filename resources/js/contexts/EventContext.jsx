import React, { createContext, useContext, useState, useEffect } from 'react';
import axios from 'axios';
import { toast } from 'react-hot-toast';

const EventContext = createContext();

export const useEvent = () => {
    const context = useContext(EventContext);
    if (!context) {
        throw new Error('useEvent must be used within an EventProvider');
    }
    return context;
};

export const EventProvider = ({ children }) => {
    const [currentEvent, setCurrentEvent] = useState(null);
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchEvents = async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await axios.get('/events');
            
            // Handle empty or invalid response
            const eventsList = response.data?.data?.events || [];
            setEvents(eventsList);
            
            // Only set current event if we have events and none is selected
            if (!currentEvent && eventsList.length > 0) {
                setCurrentEvent(eventsList[0]);
            }
        } catch (error) {
            console.error('Error fetching events:', error);
            setError(error.response?.data?.message || 'Failed to load events');
            setEvents([]);
            toast.error(error.response?.data?.message || 'Failed to load events');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchEvents();
    }, []);

    const selectEvent = (eventId) => {
        const event = events.find(e => e.id === eventId);
        if (event) {
            setCurrentEvent(event);
            return true;
        } else {
            console.error('Event not found:', eventId);
            toast.error('Selected event not found');
            return false;
        }
    };

    const value = {
        currentEvent,
        events,
        loading,
        error,
        selectEvent,
        refreshEvents: fetchEvents,
        hasEvents: events.length > 0
    };

    return (
        <EventContext.Provider value={value}>
            {children}
        </EventContext.Provider>
    );
};

export default EventContext; 