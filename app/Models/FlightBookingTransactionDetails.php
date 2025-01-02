<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightBookingTransactionDetails extends Model
{
    use HasFactory;
    protected $table = 'flight_booking_transaction_details';

    public function bookingDetails()
    {
        return $this->belongsTo(FlightBookingDetails::class, 'app_reference', 'app_reference');
    }

    // Define the relationship with FlightBookingItineraryDetails
    public function itineraryDetails()
    {
        return $this->belongsTo(FlightBookingItineraryDetails::class, 'app_reference', 'app_reference');
    }

    // Define the relationship with FlightBookingPassengerDetails
    public function passengerDetails()
    {
        return $this->belongsTo(FlightBookingPassengerDetails::class, 'app_reference', 'app_reference');
    }
}
