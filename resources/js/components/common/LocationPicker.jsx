import React, { useState, useEffect, useCallback } from 'react';
import { GoogleMap, LoadScript, Marker, Circle } from '@react-google-maps/api';
import PropTypes from 'prop-types';

const LocationPicker = ({
  initialLocation,
  radius,
  onLocationChange,
  onRadiusChange,
  height = '400px',
  showRadius = true,
  readOnly = false,
  apiKey = 'AIzaSyDlpw1bdaxmASFK2h1yjDqywNP-EtTUM4A'
}) => {
  const [currentPosition, setCurrentPosition] = useState(initialLocation || {
    lat: -6.2088, // Default to Jakarta
    lng: 106.8456
  });
  const [circleRadius, setCircleRadius] = useState(radius || 5000); // Default 5km in meters
  const [map, setMap] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const mapContainerStyle = {
    width: '100%',
    height
  };

  const options = {
    streetViewControl: false,
    mapTypeControl: false,
    fullscreenControl: true,
    zoomControl: true,
  };

  const circleOptions = {
    strokeColor: '#FF0000',
    strokeOpacity: 0.8,
    strokeWeight: 2,
    fillColor: '#FF0000',
    fillOpacity: 0.35,
    clickable: false,
    draggable: false,
    editable: !readOnly,
    visible: showRadius,
    zIndex: 1
  };

  // Get current location
  const getCurrentLocation = useCallback(() => {
    if (!navigator.geolocation) {
      setError('Geolocation is not supported by your browser');
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const pos = {
          lat: position.coords.latitude,
          lng: position.coords.longitude
        };
        setCurrentPosition(pos);
        if (map) {
          map.panTo(pos);
        }
        onLocationChange?.(pos);
        setLoading(false);
      },
      (error) => {
        setError('Error getting location: ' + error.message);
        setLoading(false);
      },
      {
        enableHighAccuracy: true,
        timeout: 5000,
        maximumAge: 0
      }
    );
  }, [map, onLocationChange]);

  useEffect(() => {
    if (!initialLocation) {
      getCurrentLocation();
    } else {
      setLoading(false);
    }
  }, [getCurrentLocation, initialLocation]);

  const handleMapClick = useCallback((e) => {
    if (readOnly) return;

    const newPos = {
      lat: e.latLng.lat(),
      lng: e.latLng.lng()
    };
    setCurrentPosition(newPos);
    onLocationChange?.(newPos);
  }, [onLocationChange, readOnly]);

  const handleRadiusChange = useCallback((e) => {
    const newRadius = e.target.value * 1000; // Convert km to meters
    setCircleRadius(newRadius);
    onRadiusChange?.(newRadius);
  }, [onRadiusChange]);

  const handleLoad = useCallback((map) => {
    setMap(map);
  }, []);

  if (error) {
    return <div className="text-red-500 p-4">{error}</div>;
  }

  if (loading) {
    return <div className="flex justify-center items-center h-[400px]">Loading map...</div>;
  }

  return (
    <div className="w-full">
      <LoadScript googleMapsApiKey={apiKey}>
        <GoogleMap
          mapContainerStyle={mapContainerStyle}
          center={currentPosition}
          zoom={13}
          onClick={handleMapClick}
          onLoad={handleLoad}
          options={options}
        >
          <Marker
            position={currentPosition}
            draggable={!readOnly}
            onDragEnd={(e) => {
              const newPos = {
                lat: e.latLng.lat(),
                lng: e.latLng.lng()
              };
              setCurrentPosition(newPos);
              onLocationChange?.(newPos);
            }}
          />
          {showRadius && (
            <Circle
              center={currentPosition}
              radius={circleRadius}
              options={circleOptions}
            />
          )}
        </GoogleMap>
      </LoadScript>
      {showRadius && !readOnly && (
        <div className="mt-4">
          <label className="block text-sm font-medium text-gray-700">
            Radius (km):
            <input
              type="range"
              min="1"
              max="50"
              value={circleRadius / 1000}
              onChange={handleRadiusChange}
              className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer mt-2"
            />
            <span className="text-sm text-gray-500 ml-2">
              {(circleRadius / 1000).toFixed(1)} km
            </span>
          </label>
        </div>
      )}
      {!readOnly && (
        <button
          onClick={getCurrentLocation}
          className="mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
        >
          Use Current Location
        </button>
      )}
    </div>
  );
};

LocationPicker.propTypes = {
  initialLocation: PropTypes.shape({
    lat: PropTypes.number,
    lng: PropTypes.number
  }),
  radius: PropTypes.number,
  onLocationChange: PropTypes.func,
  onRadiusChange: PropTypes.func,
  height: PropTypes.string,
  showRadius: PropTypes.bool,
  readOnly: PropTypes.bool,
  apiKey: PropTypes.string
};

export default LocationPicker; 