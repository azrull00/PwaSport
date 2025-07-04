import React from 'react';
import { useFormik } from 'formik';
import * as Yup from 'yup';
import LocationPicker from './LocationPicker';
import { getAddressFromCoords } from '../../utils/locationUtils';

const DAYS_OF_WEEK = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
const AMENITIES = ['Parking', 'Shower', 'Locker', 'Cafe', 'Pro Shop', 'Equipment Rental'];

const validationSchema = Yup.object({
  name: Yup.string()
    .required('Venue name is required')
    .min(3, 'Name must be at least 3 characters')
    .max(50, 'Name must be at most 50 characters'),
  description: Yup.string()
    .required('Description is required')
    .min(10, 'Description must be at least 10 characters'),
  total_courts: Yup.number()
    .required('Number of courts is required')
    .min(1, 'Must have at least 1 court')
    .max(50, 'Cannot exceed 50 courts'),
  latitude: Yup.number()
    .required('Location is required'),
  longitude: Yup.number()
    .required('Location is required'),
  address: Yup.string()
    .required('Address is required'),
  amenities: Yup.array()
    .of(Yup.string())
    .min(1, 'Select at least one amenity'),
  operating_hours: Yup.object().shape(
    DAYS_OF_WEEK.reduce((acc, day) => ({
      ...acc,
      [day]: Yup.object({
        open: Yup.string().required(`Opening time for ${day} is required`),
        close: Yup.string().required(`Closing time for ${day} is required`)
      })
    }), {})
  )
});

const VenueForm = ({
  initialValues,
  onSubmit,
  submitButtonText = 'Save',
  loading = false,
  error = null,
  onCancel = null
}) => {
  const formik = useFormik({
    initialValues: {
      name: '',
      description: '',
      total_courts: 1,
      latitude: null,
      longitude: null,
      address: '',
      amenities: [],
      operating_hours: DAYS_OF_WEEK.reduce((acc, day) => ({
        ...acc,
        [day]: { open: '08:00', close: '22:00' }
      }), {}),
      ...initialValues
    },
    validationSchema,
    onSubmit: async (values) => {
      await onSubmit(values);
    }
  });

  const handleLocationSelect = async ({ latitude, longitude }) => {
    try {
      const addressInfo = await getAddressFromCoords(latitude, longitude);
      formik.setFieldValue('latitude', latitude);
      formik.setFieldValue('longitude', longitude);
      formik.setFieldValue('address', addressInfo.fullAddress);
    } catch (error) {
      console.error('Error getting address:', error);
      formik.setFieldValue('latitude', latitude);
      formik.setFieldValue('longitude', longitude);
      formik.setFieldValue('address', 'Address not found');
    }
  };

  return (
    <form onSubmit={formik.handleSubmit} className="space-y-6">
      {error && (
        <div className="bg-red-50 text-red-500 p-4 rounded-lg">
          {error}
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label className="block text-sm font-medium text-gray-700">
            Venue Name
          </label>
          <input
            type="text"
            {...formik.getFieldProps('name')}
            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-primary focus:border-primary ${
              formik.touched.name && formik.errors.name ? 'border-red-500' : 'border-gray-300'
            }`}
          />
          {formik.touched.name && formik.errors.name && (
            <div className="text-red-500 text-sm mt-1">{formik.errors.name}</div>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">
            Total Courts
          </label>
          <input
            type="number"
            min="1"
            max="50"
            {...formik.getFieldProps('total_courts')}
            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-primary focus:border-primary ${
              formik.touched.total_courts && formik.errors.total_courts ? 'border-red-500' : 'border-gray-300'
            }`}
          />
          {formik.touched.total_courts && formik.errors.total_courts && (
            <div className="text-red-500 text-sm mt-1">{formik.errors.total_courts}</div>
          )}
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Location
        </label>
        <LocationPicker
          onLocationSelect={handleLocationSelect}
          initialLocation={
            formik.values.latitude && formik.values.longitude
              ? { lat: formik.values.latitude, lng: formik.values.longitude }
              : null
          }
          height="300px"
        />
        {((formik.touched.latitude && formik.errors.latitude) ||
          (formik.touched.longitude && formik.errors.longitude)) && (
          <div className="text-red-500 text-sm mt-1">Location is required</div>
        )}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700">
          Description
        </label>
        <textarea
          {...formik.getFieldProps('description')}
          rows={3}
          className={`mt-1 block w-full rounded-md shadow-sm focus:ring-primary focus:border-primary ${
            formik.touched.description && formik.errors.description ? 'border-red-500' : 'border-gray-300'
          }`}
        />
        {formik.touched.description && formik.errors.description && (
          <div className="text-red-500 text-sm mt-1">{formik.errors.description}</div>
        )}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Amenities
        </label>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          {AMENITIES.map(amenity => (
            <label key={amenity} className="inline-flex items-center">
              <input
                type="checkbox"
                checked={formik.values.amenities.includes(amenity)}
                onChange={() => {
                  const currentAmenities = formik.values.amenities;
                  const newAmenities = currentAmenities.includes(amenity)
                    ? currentAmenities.filter(a => a !== amenity)
                    : [...currentAmenities, amenity];
                  formik.setFieldValue('amenities', newAmenities);
                }}
                className="rounded border-gray-300 text-primary focus:ring-primary"
              />
              <span className="ml-2">{amenity}</span>
            </label>
          ))}
        </div>
        {formik.touched.amenities && formik.errors.amenities && (
          <div className="text-red-500 text-sm mt-1">{formik.errors.amenities}</div>
        )}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Operating Hours
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {DAYS_OF_WEEK.map(day => (
            <div key={day} className="flex items-center space-x-4">
              <span className="w-24 capitalize">{day}</span>
              <div className="flex space-x-2 items-center">
                <input
                  type="time"
                  {...formik.getFieldProps(`operating_hours.${day}.open`)}
                  className={`rounded-md shadow-sm focus:ring-primary focus:border-primary ${
                    formik.touched.operating_hours?.[day]?.open && 
                    formik.errors.operating_hours?.[day]?.open 
                      ? 'border-red-500' 
                      : 'border-gray-300'
                  }`}
                />
                <span>to</span>
                <input
                  type="time"
                  {...formik.getFieldProps(`operating_hours.${day}.close`)}
                  className={`rounded-md shadow-sm focus:ring-primary focus:border-primary ${
                    formik.touched.operating_hours?.[day]?.close && 
                    formik.errors.operating_hours?.[day]?.close 
                      ? 'border-red-500' 
                      : 'border-gray-300'
                  }`}
                />
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="flex justify-end space-x-3">
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
          >
            Cancel
          </button>
        )}
        <button
          type="submit"
          disabled={loading || !formik.isValid}
          className={`px-4 py-2 rounded-lg text-white ${
            loading || !formik.isValid
              ? 'bg-gray-400 cursor-not-allowed'
              : 'bg-primary hover:bg-primary-dark'
          }`}
        >
          {loading ? (
            <div className="flex items-center space-x-2">
              <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <span>Saving...</span>
            </div>
          ) : (
            submitButtonText
          )}
        </button>
      </div>
    </form>
  );
};

export default VenueForm; 