<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightBookingPassengerDetails extends Model
{
    use HasFactory;

    protected $table = 'flight_booking_passenger_details';

    public function bookingDetails()
    {
        return $this->belongsTo(FlightBookingDetails::class, 'app_reference', 'app_reference');
    }

    // Define the relationship with FlightBookingItineraryDetails
    public function itineraryDetails()
    {
        return $this->belongsTo(FlightBookingItineraryDetails::class, 'app_reference', 'app_reference');
    }

    // Define the relationship with FlightBookingTransactionDetails
    public function transactionDetails()
    {
        return $this->hasMany(FlightBookingTransactionDetails::class, 'app_reference', 'app_reference');
    }
}
