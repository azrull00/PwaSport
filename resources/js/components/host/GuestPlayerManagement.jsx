import React, { useState, useEffect } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { toast } from 'react-hot-toast';
import { HiUserAdd, HiPhone, HiStar, HiChartBar, HiRefresh, HiTrash } from 'react-icons/hi';
import axios from 'axios';

const GuestPlayerManagement = ({ eventId, onGuestAdded }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [guestPlayers, setGuestPlayers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [newGuest, setNewGuest] = useState({
        name: '',
        phone: '',
        skill_level: 'beginner',
        estimated_mmr: 1000,
    });

    const skillLevels = [
        { value: 'beginner', label: 'Beginner', mmr: '800-1000', color: 'bg-gray-100 text-gray-800' },
        { value: 'intermediate', label: 'Intermediate', mmr: '1000-1200', color: 'bg-blue-100 text-blue-800' },
        { value: 'advanced', label: 'Advanced', mmr: '1200-1600', color: 'bg-green-100 text-green-800' },
        { value: 'expert', label: 'Expert', mmr: '1600-2000', color: 'bg-purple-100 text-purple-800' },
    ];

    const fetchGuestPlayers = async (showLoading = false) => {
        if (showLoading) setRefreshing(true);
        try {
            const response = await axios.get(`/api/events/${eventId}/guest-players`);
            setGuestPlayers(response.data.guests);
        } catch (error) {
            toast.error('Failed to load guest players');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        fetchGuestPlayers(true);
        const interval = setInterval(() => fetchGuestPlayers(false), 30000);
        return () => clearInterval(interval);
    }, [eventId]);

    const handleSkillLevelChange = (level) => {
        const mmrRanges = {
            beginner: 800,
            intermediate: 1000,
            advanced: 1200,
            expert: 1600,
        };
        setNewGuest({
            ...newGuest,
            skill_level: level,
            estimated_mmr: mmrRanges[level],
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            const response = await axios.post(`/api/events/${eventId}/guest-players`, newGuest);
            toast.success('Guest player added successfully');
            setGuestPlayers([...guestPlayers, response.data.guest]);
            onGuestAdded(response.data.guest);
            setNewGuest({
                name: '',
                phone: '',
                skill_level: 'beginner',
                estimated_mmr: 1000,
            });
            setIsOpen(false);
        } catch (error) {
            toast.error('Failed to add guest player');
        }
    };

    const handleRemoveGuest = async (guestId) => {
        if (!confirm('Are you sure you want to remove this guest player?')) return;
        
        try {
            await axios.delete(`/api/events/${eventId}/guest-players/${guestId}`);
            toast.success('Guest player removed successfully');
            setGuestPlayers(guestPlayers.filter(guest => guest.id !== guestId));
        } catch (error) {
            toast.error('Failed to remove guest player');
        }
    };

    const getSkillLevelBadge = (skillLevel) => {
        const level = skillLevels.find(l => l.value === skillLevel);
        return level ? level.color : 'bg-gray-100 text-gray-800';
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[200px]">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center space-x-4">
                    <h2 className="text-lg font-medium text-gray-900">Guest Players</h2>
                    <button
                        onClick={() => fetchGuestPlayers(true)}
                        className={`p-2 text-gray-500 hover:text-gray-700 rounded-full hover:bg-gray-100 ${
                            refreshing ? 'animate-spin' : ''
                        }`}
                    >
                        <HiRefresh className="w-5 h-5" />
                    </button>
                </div>
                <button
                    onClick={() => setIsOpen(true)}
                    className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    <HiUserAdd className="w-5 h-5 mr-2" />
                    Add Guest Player
                </button>
            </div>

            {/* Guest Players Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {guestPlayers.map((guest) => (
                    <div key={guest.id} className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center space-x-3">
                                <div className="flex-shrink-0">
                                    <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <span className="text-lg font-medium text-blue-700">
                                            {guest.name.charAt(0)}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <h3 className="text-sm font-medium text-gray-900">{guest.name}</h3>
                                    {guest.phone && (
                                        <p className="text-sm text-gray-500 flex items-center mt-1">
                                            <HiPhone className="w-4 h-4 mr-1" />
                                            {guest.phone}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <button
                                onClick={() => handleRemoveGuest(guest.id)}
                                className="text-gray-400 hover:text-red-500"
                            >
                                <HiTrash className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="mt-4 flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getSkillLevelBadge(guest.skill_level)}`}>
                                    <HiStar className="w-4 h-4 mr-1" />
                                    {guest.skill_level.charAt(0).toUpperCase() + guest.skill_level.slice(1)}
                                </span>
                                <span className="inline-flex items-center text-sm text-gray-500">
                                    <HiChartBar className="w-4 h-4 mr-1" />
                                    MMR: {guest.estimated_mmr}
                                </span>
                            </div>
                        </div>
                    </div>
                ))}
                {guestPlayers.length === 0 && (
                    <div className="col-span-full text-center py-8 text-gray-500">
                        No guest players added yet
                    </div>
                )}
            </div>

            {/* Add Guest Modal */}
            <Transition show={isOpen} as={React.Fragment}>
                <Dialog
                    as="div"
                    className="fixed z-10 inset-0 overflow-y-auto"
                    onClose={() => setIsOpen(false)}
                >
                    <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <Transition.Child
                            as={React.Fragment}
                            enter="ease-out duration-300"
                            enterFrom="opacity-0"
                            enterTo="opacity-100"
                            leave="ease-in duration-200"
                            leaveFrom="opacity-100"
                            leaveTo="opacity-0"
                        >
                            <Dialog.Overlay className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                        </Transition.Child>

                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <Transition.Child
                            as={React.Fragment}
                            enter="ease-out duration-300"
                            enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            enterTo="opacity-100 translate-y-0 sm:scale-100"
                            leave="ease-in duration-200"
                            leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                            leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        >
                            <div className="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                                <div>
                                    <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                                        <HiUserAdd className="h-6 w-6 text-blue-600" />
                                    </div>
                                    <div className="mt-3 text-center sm:mt-5">
                                        <Dialog.Title as="h3" className="text-lg leading-6 font-medium text-gray-900">
                                            Add Guest Player
                                        </Dialog.Title>
                                    </div>
                                </div>

                                <form onSubmit={handleSubmit} className="mt-5 sm:mt-6">
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Name
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={newGuest.name}
                                                onChange={(e) => setNewGuest({ ...newGuest, name: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Phone (Optional)
                                            </label>
                                            <input
                                                type="tel"
                                                value={newGuest.phone}
                                                onChange={(e) => setNewGuest({ ...newGuest, phone: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Skill Level
                                            </label>
                                            <div className="mt-1 grid grid-cols-2 gap-3">
                                                {skillLevels.map((level) => (
                                                    <button
                                                        key={level.value}
                                                        type="button"
                                                        onClick={() => handleSkillLevelChange(level.value)}
                                                        className={`${
                                                            newGuest.skill_level === level.value
                                                                ? 'ring-2 ring-blue-500'
                                                                : 'border-2 border-gray-200'
                                                        } p-3 rounded-lg text-left focus:outline-none`}
                                                    >
                                                        <p className="text-sm font-medium text-gray-900">{level.label}</p>
                                                        <p className="text-xs text-gray-500">MMR: {level.mmr}</p>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Estimated MMR
                                            </label>
                                            <input
                                                type="number"
                                                min="0"
                                                max="3000"
                                                value={newGuest.estimated_mmr}
                                                onChange={(e) => setNewGuest({ ...newGuest, estimated_mmr: parseInt(e.target.value) })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            />
                                        </div>
                                    </div>

                                    <div className="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3">
                                        <button
                                            type="button"
                                            onClick={() => setIsOpen(false)}
                                            className="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            className="mt-3 sm:mt-0 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm"
                                        >
                                            Add Guest
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </Transition.Child>
                    </div>
                </Dialog>
            </Transition>
        </div>
    );
};

export default GuestPlayerManagement; 