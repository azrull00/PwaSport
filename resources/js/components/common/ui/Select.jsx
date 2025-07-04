import React from 'react';

const Select = ({
  label,
  error,
  helper,
  id,
  name,
  value,
  onChange,
  onBlur,
  options = [],
  placeholder = 'Select an option',
  disabled = false,
  required = false,
  className = '',
  containerClassName = '',
  labelClassName = '',
  selectClassName = '',
  ...props
}) => {
  const selectId = id || name;

  return (
    <div className={`space-y-1 ${containerClassName}`}>
      {label && (
        <label
          htmlFor={selectId}
          className={`block text-sm font-medium text-gray-700 ${labelClassName}`}
        >
          {label}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      <select
        id={selectId}
        name={name}
        value={value}
        onChange={onChange}
        onBlur={onBlur}
        disabled={disabled}
        required={required}
        className={`
          block w-full rounded-md sm:text-sm
          ${error ? 'border-red-300 text-red-900 focus:ring-red-500 focus:border-red-500' : 
                   'border-gray-300 focus:ring-primary focus:border-primary'}
          ${disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
          ${selectClassName}
        `}
        {...props}
      >
        <option value="" disabled>
          {placeholder}
        </option>
        {options.map((option) => (
          <option
            key={option.value}
            value={option.value}
            disabled={option.disabled}
          >
            {option.label}
          </option>
        ))}
      </select>
      {helper && !error && (
        <p className="mt-1 text-sm text-gray-500">{helper}</p>
      )}
      {error && (
        <p className="mt-1 text-sm text-red-600">{error}</p>
      )}
    </div>
  );
};

export default Select; 