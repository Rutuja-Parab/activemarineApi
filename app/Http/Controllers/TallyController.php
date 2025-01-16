<?php

namespace App\Http\Controllers;

use App\Models\FlightBookingDetails;
use App\Models\TallyInvoice;
use App\Models\TallySync;
use App\Models\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TallyController extends Controller
{
    public function getAllData(Request $request)
    {
        ini_set('max_execution_time', 300);
        $data = null;
        DB::beginTransaction(); // Start the transaction

        try {
            if ($request->has('data') && !empty($request->input('data'))) {
                $data = $request->input('data');
                foreach ($data as $d) {
                    // Extract individual fields from the request data
                    $transactionNumber = $d['TransactionNumber'];
                    $syncStatus = $d['sync_status'];
                    $message = $d['message'];

                    // Insert into the tally_invoices table
                    TallyInvoice::create([
                        'invoice_no' => $transactionNumber,  // mapping TransactionNumber to invoice_no
                        'status' => $syncStatus,              // mapping sync_status to status
                        'sync_time' => now(),                 // setting the current timestamp
                        'remarks' => null,                     // setting remark as null
                        'message' => $message,                // mapping message to message
                    ]);
                }
            }
            $lastBatch = TallySync::latest()->first();
            $batchNo = $lastBatch ? $lastBatch->batch_no : 0;
            $offset = $batchNo * 100;

            $flightBookingDetails = FlightBookingDetails::with(['itineraryDetails', 'passengerDetails', 'transactionDetails', 'corporateDetails'])
                ->where('invoice_no', '!=', '')
                ->offset($offset)
                ->limit(100)
                ->get()
                ->toArray();


            $allSales = []; // Collect all sales data here

            foreach ($flightBookingDetails as $flightBooking) {
                $sales = [];
                $pnr = '';

                // Calculating Invoice Type
                $sales['VchType'] = isset($flightBooking['invoice_no']) && strpos($flightBooking['invoice_no'], 'CN') !== false
                    ? 'Credit Note'
                    : 'Sales';

                // Getting Invoice Number
                $sales['TransactionNumber'] = $flightBooking['invoice_no'];

                // Getting Today's Date
                $sales['TransactionDate'] = (new DateTime())->format('d-m-Y');

                // Getting Reference No
                $sales['ReferenceNo'] = $flightBooking['invoice_no'];

                // Getting Reference Date
                $sales['ReferenceDate'] = (new DateTime($flightBooking['created_datetime']))->format('d-m-Y');

                // Getting All Itinerary Description
                $invoiceNo = $flightBooking['invoice_no'] ?? '';
                $passengerDetails = $flightBooking['passenger_details'] ?? [];
                $itineraryDetails = $flightBooking['itinerary_details'] ?? [];

                // Prepare descriptions for multiple passengers and sectors
                $passengerDescriptions = [];
                foreach ($passengerDetails as $key => $passenger) {
                    $passengerName = trim(($passenger['first_name'] ?? '') . ' ' . ($passenger['last_name'] ?? ''));
                    $pnr = $itineraryDetails[$key]['airline_pnr'] ?? '';

                    // Build the passenger description
                    $passengerDescriptionParts = [];
                    if (!empty($passengerName)) {
                        $passengerDescriptionParts[] = "Passenger Name: $passengerName";
                    }

                    if (!empty($pnr)) {
                        $passengerDescriptionParts[] = "PNR: $pnr";
                    }

                    if (!empty($passengerDescriptionParts)) {
                        $passengerDescriptions[] = implode(', ', $passengerDescriptionParts);
                    }
                }

                // Prepare itinerary descriptions (sector details)
                $itineraryDescriptions = [];
                foreach ($itineraryDetails as $key => $itinerary) {
                    $fromAirport = $itinerary['from_airport_name'] ?? '';
                    $toAirport = $itinerary['to_airport_name'] ?? '';

                    // Create sector description
                    $sector = '';
                    if (!empty($fromAirport) && !empty($toAirport)) {
                        $sector = $fromAirport . ' - ' . $toAirport;
                    }

                    // Add sector to itinerary description
                    if (!empty($sector)) {
                        $itineraryDescriptions[] = "Sector: $sector";
                    }
                }

                // Combine the final invoice description
                $sales['InvoiceDesc'] = "Invoice No: $invoiceNo, " . implode(' | ', $passengerDescriptions) .
                    (count($itineraryDescriptions) > 0 ? ' | ' . implode(' | ', $itineraryDescriptions) : '');

                // Getting Invoice Amount
                $transactionDetails = $flightBooking['transaction_details'] ?? [];
                $sales['InvoiceAmount'] = $transactionDetails[0]['total_fare'] ?? 0;

                // for getting PartyName
                $corporateDetails = $flightBooking['corporate_details'] ?? [];
                $sales['PartyName'] = $corporateDetails['company_name'];
                $sales['PartyInfo'] = ['Name' => $corporateDetails['company_name']];
                $sales['PartyBillwiseDetails'] = [
                    'BillReferenceNumber' => $flightBooking['invoice_no'],
                    'BillAmount' => $sales['InvoiceAmount'],
                ];

                if (isset($flightBooking['invoice_no']) && strpos($flightBooking['invoice_no'], 'DS') !== false) {
                    $ledgerName = 'Air Ticket Issue Fare- DOM (Charges/Income)';
                } else if (isset($flightBooking['invoice_no']) && strpos($flightBooking['invoice_no'], 'IS') !== false) {
                    $ledgerName = 'Air Ticket Issue Fare- INT (Charges/Income)';
                } else {
                    $ledgerName = '';
                }

                $fare_attributes = json_decode($flightBooking['total_price_attributes'], true);
                $totalFare = $fare_attributes['api_total_display_fare'] - ($fare_attributes['total_breakup']['admin_markup'] + $fare_attributes['total_breakup']['admin_markup_gst']);

                $pnrArray = explode(' / ', $pnr);
                $ledgerDetails = [];

                // Iterate over each PNR in the array
                foreach ($pnrArray as $index => $pnr) {
                    // Dynamically get the base_fare and tax_fare keys based on the index
                    $baseFareKey = "base_fare" . ($index + 1);
                    $taxFareKey = "tax_fare" . ($index + 1);

                    // Extracting the base fare and tax fare values
                    $baseFare = $fare_attributes['total_breakup']['individual'][$baseFareKey] ?? 0;
                    $taxFare = $fare_attributes['total_breakup']['individual'][$taxFareKey] ?? 0;

                    // Calculate the BillAmount
                    $billAmount = $baseFare + $taxFare;

                    // Prepare the ledger details for each PNR
                    $ledgerDetails[] = [
                        'BillReferenceNumber' => $pnr,
                        'BillAmount' => $billAmount ?? $totalFare,
                    ];
                }

                // Ticket Fees
                $ticketFees = [
                    'LedgerName' => $ledgerName,
                    'Amount' => $totalFare,
                    'CostCenterName' => 'Ticket Issue',
                    'CCAmount' => $totalFare,
                    'BillwiseDetails' => $ledgerDetails
                ];

                $sales['LedgerDrCR'][] = $ticketFees;

                // Management Fees
                if (isset($fare_attributes['total_breakup']['admin_markup'])) {
                    $managementFees = [
                        'LedgerName' => 'Ticket Processing Fees (Income)',
                        'Amount' => $fare_attributes['total_breakup']['admin_markup'],
                        'CostCenterName' => 'Travel Desk',
                        'CCAmount' => $fare_attributes['total_breakup']['admin_markup'],
                        'BillwiseDetails' => []
                    ];
                    $sales['LedgerDrCR'][] = $managementFees;
                }

                // CGST Fees
                if (isset($fare_attributes['total_breakup']['admin_markup_gst'])) {
                    $cgstFees = [
                        'LedgerName' => 'CGST',
                        'Amount' => $fare_attributes['total_breakup']['admin_markup_gst'] / 2,
                        'CostCenterName' => null,
                        'CCAmount' => 0.00,
                        'BillwiseDetails' => []
                    ];
                    $sales['LedgerDrCR'][] = $cgstFees;
                }

                // SGST Fees
                if (isset($fare_attributes['total_breakup']['admin_markup_gst'])) {
                    $sgstFees = [
                        'LedgerName' => 'SGST',
                        'Amount' => $fare_attributes['total_breakup']['admin_markup_gst'] / 2,
                        'CostCenterName' => null,
                        'CCAmount' => 0.00,
                        'BillwiseDetails' => []
                    ];
                    $sales['LedgerDrCR'][] = $sgstFees;
                }

                // Add the current sales data to the allSales array
                $allSales[] = $sales;
            }
            if (!empty($allSales) && count($allSales) > 0 && !empty($data)) {
                TallySync::create([
                    'batch_no' => $batchNo + 1,
                    'sync_time' => now(), // Current timestamp
                    'request_data' => json_encode($data), // Store request data as JSON in the blob field
                    'response_data' => json_encode($allSales), // Store response data as JSON in the blob field
                ]);
            }

            DB::commit(); // Commit the transaction if everything goes smoothly
            return response()->json(['Sales' => $allSales], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback the transaction in case of error
            return response()->json(['error' => 'Failed to process data', 'message' => $e->getMessage()], 500);
        }
    }
}
