<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightBookingDetails extends Model
{
    use HasFactory;

    protected $table = 'flight_booking_details';

    // Define the relationship with FlightBookingItineraryDetails
    public function itineraryDetails()
    {
        return $this->hasMany(FlightBookingItineraryDetails::class, 'app_reference', 'app_reference');
    }

    // Define the relationship with FlightBookingPassengerDetails
    public function passengerDetails()
    {
        return $this->hasMany(FlightBookingPassengerDetails::class, 'app_reference', 'app_reference');
    }

    // Define the relationship with FlightBookingTransactionDetails
    public function transactionDetails()
    {
        return $this->hasMany(FlightBookingTransactionDetails::class, 'app_reference', 'app_reference');
    }

    public function corporateDetails()
    {
        return $this->belongsTo(User::class, 'corporate_id', 'user_id');
    }
}
