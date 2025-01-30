<?php

namespace App\Http\Controllers;

use App\Models\FlightBookingDetails;
use App\Models\TallyInvoice;
use App\Models\TallySync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;
use Illuminate\Support\Carbon;

class TallyController extends Controller
{
    /**
     * Get the next batch of invoices for processing.
     */
    public function getAllData(Request $request)
    {
        ini_set('max_execution_time', 300);
        $batchSize = 10;

        DB::beginTransaction();
        try {
            // Get the last batch number
            $lastBatch = TallySync::latest()->first();
            $batchNo = $lastBatch ? $lastBatch->batch_no : 0;
            $offset = $batchNo * $batchSize;

            // Fetch next batch of invoices
            $flightBookingDetails = FlightBookingDetails::with(['itineraryDetails', 'passengerDetails', 'transactionDetails', 'corporateDetails'])
                ->where('invoice_no', '!=', '')
                ->offset($offset)
                ->limit($batchSize)
                ->get();

            if ($flightBookingDetails->isEmpty()) {
                return response()->json(['message' => 'No more invoices to process'], 200);
            }

            $allSales = [];

            foreach ($flightBookingDetails as $flightBooking) {
                $sales = [
                    'VchType'         => strpos($flightBooking->invoice_no, 'CN') !== false ? 'Credit Note' : 'Sales',
                    'TransactionNumber' => $flightBooking->invoice_no,
                    'TransactionDate' => now()->format('d-m-Y'),
                    'ReferenceNo'     => $flightBooking->invoice_no,
                    'ReferenceDate'   => (new DateTime($flightBooking->created_datetime))->format('d-m-Y'),
                    'InvoiceDesc'     => "Invoice No: {$flightBooking->invoice_no}",
                    'InvoiceAmount'   => optional($flightBooking->transactionDetails->first())->total_fare ?? 0,
                    'PartyName'       => optional($flightBooking->corporateDetails)->company_name,
                    'PartyInfo'       => ['Name' => optional($flightBooking->corporateDetails)->company_name],
                    'PartyBillwiseDetails' => [
                        'BillReferenceNumber' => $flightBooking->invoice_no,
                        'BillAmount'          => optional($flightBooking->transactionDetails->first())->total_fare ?? 0,
                    ],
                    'LedgerDrCR' => []
                ];

                $allSales[] = $sales;
            }

            // Save batch record
            TallySync::create([
                'batch_no'      => $batchNo + 1,
                'sync_time'     => now(),
                'response_data' => json_encode($allSales),
            ]);

            DB::commit();
            return response()->json(['Sales' => $allSales], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to process data', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store the response of sent invoices.
     */
    public function postAllData(Request $request)
    {
        ini_set('max_execution_time', 300);

        $validatedData = $request->validate([
            'data' => 'required|array',
            'data.*.TransactionNumber' => 'required|string|max:255',
            'data.*.sync_status' => 'required|string|max:50',
            'data.*.Remarks' => 'nullable|string|max:500',
            'data.*.Date' => 'string|max:500',
        ]);
        DB::beginTransaction();
        try {
            foreach ($validatedData['data'] as $d) {
                $formattedDate = null;
                if (!empty($d['Date'])) {
                    $formattedDate = Carbon::createFromFormat('d/m/Y', $d['Date'])->format('Y-m-d');
                }
                TallyInvoice::updateOrCreate(
                    ['invoice_no' => $d['TransactionNumber']],
                    [
                        'status'     => $d['sync_status'],
                        'sync_time'  => now(),
                        'date'  => $formattedDate,
                        'remarks'    => $d['Remarks'] ?? null,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'status'        => 'success',
                'message'       => 'Invoices successfully stored',
                'total_records' => count($validatedData['data']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to process data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
