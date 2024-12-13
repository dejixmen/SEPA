<?php

namespace App\Http\Controllers;

use App\Models\SepaMandate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Mandate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SepaMandateController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function index()
    {
        $mandates = SepaMandate::orderBy('created_at', 'desc')->paginate(10);
        return view('sepa.index', compact('mandates'));
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sepa_mandates_template.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['reference', 'name', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'postal_code', 'country', 'iban', 'bic', 'amount', 'signed_date']);
            fputcsv($file, ['SEPA-001', 'John Doe', 'john@example.com', '+49123456789', 'Musterstrasse 123', '2nd Floor', 'Berlin', '10115', 'DE', 'DE89370400440532013000', 'DEUTDEFF', '100.00', date('Y-m-d')]);
            fputcsv($file, ['SEPA-002', 'Jane Smith', 'jane@example.com', '+49987654321', 'Hauptstrasse 45', 'Apt 3B', 'Munich', '80331', 'DE', 'FR1420041010050500013M02606', 'BNPAFRPP', '150.00', date('Y-m-d')]);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        // Increase timeout limit for this request
        set_time_limit(300); // Set to 5 minutes
        ini_set('max_execution_time', 300);

        Log::info('Starting SEPA mandate import');

        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,csv'
            ]);

            if (!$request->hasFile('file')) {
                return redirect()->back()->with('error', 'No file was uploaded');
            }

            $file = $request->file('file');
            $data = Excel::toCollection(null, $file)->first();
            
            if ($data->isEmpty()) {
                return redirect()->back()->with('error', 'The uploaded file is empty');
            }

            // Get headers from first row
            $headers = $data[0]->toArray();

            // Validate required columns
            $requiredColumns = ['reference', 'name', 'email', 'iban', 'amount', 'signed_date'];
            $missingColumns = array_diff($requiredColumns, $headers);
            
            if (!empty($missingColumns)) {
                return redirect()->back()->with('error', 'Missing required columns: ' . implode(', ', $missingColumns));
            }

            $importedCount = 0;
            $errorCount = 0;
            $errors = [];
            
            // Process in batches of 5
            $rows = $data->slice(1)->chunk(5);
            
            foreach ($rows as $batchIndex => $batch) {
                DB::beginTransaction();
                try {
                    foreach ($batch as $index => $row) {
                        try {
                            // Map row data to associative array using headers
                            $rowData = array_combine($headers, $row->toArray());
                            
                            // Clean up the data
                            foreach ($rowData as $key => $value) {
                                if (is_string($value)) {
                                    $rowData[$key] = trim(preg_replace('/\s+/', ' ', $value));
                                }
                            }

                            // Validate row data
                            if (empty($rowData['reference']) || empty($rowData['name']) || 
                                empty($rowData['email']) || empty($rowData['iban']) || 
                                empty($rowData['amount']) || empty($rowData['signed_date'])) {
                                throw new \Exception('Missing required fields');
                            }

                            // Parse signed date
                            try {
                                $dateFormats = ['n/j/Y', 'Y-m-d', 'd/m/Y', 'Y/m/d'];
                                $signedDate = null;
                                
                                foreach ($dateFormats as $format) {
                                    try {
                                        $signedDate = Carbon::createFromFormat($format, $rowData['signed_date']);
                                        if ($signedDate) break;
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                                
                                if (!$signedDate) {
                                    throw new \Exception('Could not parse date');
                                }
                            } catch (\Exception $e) {
                                throw new \Exception('Invalid date format. Use MM/DD/YYYY');
                            }

                            // Create Stripe Customer
                            $customerData = [
                                'email' => $rowData['email'],
                                'name' => $rowData['name'],
                                'phone' => $rowData['phone'] ?? null,
                            ];

                            if (!empty($rowData['address_line1'])) {
                                $customerData['address'] = array_filter([
                                    'line1' => $rowData['address_line1'],
                                    'line2' => $rowData['address_line2'] ?? null,
                                    'city' => $rowData['city'] ?? null,
                                    'postal_code' => $rowData['postal_code'] ?? null,
                                    'country' => $rowData['country'] ?? null,
                                ]);
                            }

                            $customer = Customer::create($customerData);

                            // Create SEPA Payment Method
                            $paymentMethod = PaymentMethod::create([
                                'type' => 'sepa_debit',
                                'sepa_debit' => [
                                    'iban' => $rowData['iban'],
                                ],
                                'billing_details' => [
                                    'name' => $rowData['name'],
                                    'email' => $rowData['email'],
                                ],
                            ]);

                            // Attach Payment Method to Customer
                            $paymentMethod->attach(['customer' => $customer->id]);

                            // Create Setup Intent
                            $setupIntent = \Stripe\SetupIntent::create([
                                'payment_method_types' => ['sepa_debit'],
                                'customer' => $customer->id,
                                'payment_method' => $paymentMethod->id,
                                'mandate_data' => [
                                    'customer_acceptance' => [
                                        'type' => 'online',
                                        'online' => [
                                            'ip_address' => request()->ip(),
                                            'user_agent' => request()->userAgent()
                                        ],
                                        'accepted_at' => time(),
                                    ],
                                ],
                                'confirm' => true,
                            ]);

                            // Create SEPA Mandate
                            $sepaMandate = new SepaMandate();
                            $sepaMandate->reference = $rowData['reference'];
                            $sepaMandate->customer_name = $rowData['name'];
                            $sepaMandate->customer_email = $rowData['email'];
                            $sepaMandate->phone = $rowData['phone'] ?? null;
                            $sepaMandate->address_line1 = $rowData['address_line1'] ?? null;
                            $sepaMandate->address_line2 = $rowData['address_line2'] ?? null;
                            $sepaMandate->city = $rowData['city'] ?? null;
                            $sepaMandate->postal_code = $rowData['postal_code'] ?? null;
                            $sepaMandate->country = $rowData['country'] ?? null;
                            $sepaMandate->iban = $rowData['iban'];
                            $sepaMandate->bic = $rowData['bic'] ?? null;
                            $sepaMandate->amount = $rowData['amount'];
                            $sepaMandate->currency = 'EUR';
                            $sepaMandate->stripe_customer_id = $customer->id;
                            $sepaMandate->stripe_payment_method_id = $paymentMethod->id;
                            $sepaMandate->status = 'active';
                            $sepaMandate->payment_status = 'not_charged';
                            $sepaMandate->signed_date = $signedDate;
                            $sepaMandate->is_recurring = true;
                            $sepaMandate->billing_day = $signedDate->day;
                            $sepaMandate->next_payment_date = $signedDate->addMonth();
                            $sepaMandate->save();

                            $importedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $errors[] = "Row " . ($batchIndex * 5 + $index + 2) . ": " . $e->getMessage();
                            // Clean up Stripe resources if needed
                            if (isset($paymentMethod)) {
                                try {
                                    $paymentMethod->detach();
                                } catch (\Exception $e) {}
                            }
                            if (isset($customer)) {
                                try {
                                    $customer->delete();
                                } catch (\Exception $e) {}
                            }
                            continue;
                        }
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Batch import failed: ' . $e->getMessage());
                    $errors[] = "Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage();
                }
            }

            $message = "Import completed. Successfully imported: $importedCount";
            if ($errorCount > 0) {
                $message .= ", Failed: $errorCount";
                return redirect()->back()
                    ->with('warning', $message)
                    ->with('errors', $errors);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function charge(SepaMandate $mandate)
    {
        try {
            Log::info('Starting charge process', [
                'mandate_id' => $mandate->id,
                'reference' => $mandate->reference,
                'payment_method_id' => $mandate->stripe_payment_method_id,
                'customer_id' => $mandate->stripe_customer_id
            ]);

            if (empty($mandate->stripe_payment_method_id)) {
                throw new \Exception('No payment method ID found for this mandate');
            }

            // Create the payment intent
            $paymentIntentData = [
                'amount' => (int)($mandate->amount * 100),
                'currency' => $mandate->currency,
                'customer' => $mandate->stripe_customer_id,
                'payment_method' => $mandate->stripe_payment_method_id,
                'payment_method_types' => ['sepa_debit'],
                'confirm' => true,
                'return_url' => route('sepa.index'),
                'payment_method_options' => [
                    'sepa_debit' => [
                        'setup_future_usage' => 'off_session'
                    ],
                ],
                'mandate_data' => [
                    'customer_acceptance' => [
                        'type' => 'online',
                        'online' => [
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent()
                        ],
                        'accepted_at' => time(),
                    ],
                ],
                'metadata' => [
                    'is_recurring' => 'true',
                    'billing_day' => $mandate->billing_day,
                    'mandate_reference' => $mandate->reference
                ]
            ];

            Log::info('Creating payment intent with data', array_merge(
                $paymentIntentData,
                ['mandate_id' => $mandate->id]
            ));

            $paymentIntent = \Stripe\PaymentIntent::create($paymentIntentData);

            // Update mandate with payment info and next payment date
            $mandate->update([
                'payment_status' => $paymentIntent->status,
                'last_payment_date' => now(),
                'last_payment_id' => $paymentIntent->id,
                'next_payment_date' => Carbon::now()->addMonth()->day($mandate->billing_day)
            ]);

            Log::info('Updated mandate with next payment date', [
                'mandate_id' => $mandate->id,
                'next_payment_date' => $mandate->next_payment_date
            ]);

            return redirect()->back()->with('success', 'Payment initiated successfully. Status: ' . $paymentIntent->status);
        } catch (\Exception $e) {
            Log::error('Payment Error: ' . $e->getMessage(), [
                'mandate_id' => $mandate->id,
                'reference' => $mandate->reference,
                'payment_method_id' => $mandate->stripe_payment_method_id ?? null,
                'customer_id' => $mandate->stripe_customer_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }

    public function chargeAll()
    {
        $mandates = SepaMandate::where('status', 'active')
            ->where('payment_status', 'not_charged')
            ->where('next_payment_date', '<=', now())
            ->get();

        Log::info('Starting bulk charge process', ['mandate_count' => $mandates->count()]);

        $successCount = 0;
        $failureCount = 0;

        foreach ($mandates as $mandate) {
            try {
                // Create the payment intent
                $paymentIntentData = [
                    'amount' => (int)($mandate->amount * 100),
                    'currency' => $mandate->currency,
                    'customer' => $mandate->stripe_customer_id,
                    'payment_method' => $mandate->stripe_payment_method_id,
                    'payment_method_types' => ['sepa_debit'],
                    'confirm' => true,
                    'return_url' => route('sepa.index'),
                    'payment_method_options' => [
                        'sepa_debit' => [
                            'setup_future_usage' => 'off_session'
                        ],
                    ],
                    'mandate_data' => [
                        'customer_acceptance' => [
                            'type' => 'online',
                            'online' => [
                                'ip_address' => request()->ip(),
                                'user_agent' => request()->userAgent()
                            ],
                            'accepted_at' => time(),
                        ],
                    ],
                    'metadata' => [
                        'is_recurring' => 'true',
                        'billing_day' => $mandate->billing_day,
                        'mandate_reference' => $mandate->reference
                    ]
                ];

                $paymentIntent = \Stripe\PaymentIntent::create($paymentIntentData);

                // Update mandate with payment info and next payment date
                $mandate->update([
                    'payment_status' => $paymentIntent->status,
                    'last_payment_date' => now(),
                    'last_payment_id' => $paymentIntent->id,
                    'next_payment_date' => Carbon::now()->addMonth()->day($mandate->billing_day)
                ]);

                $successCount++;
                Log::info('Successfully charged mandate', [
                    'mandate_id' => $mandate->id,
                    'payment_status' => $paymentIntent->status,
                    'next_payment_date' => $mandate->next_payment_date
                ]);
            } catch (\Exception $e) {
                Log::error('Bulk Payment Error for mandate ' . $mandate->reference . ': ' . $e->getMessage(), [
                    'mandate_id' => $mandate->id,
                    'payment_method_id' => $mandate->stripe_payment_method_id ?? null,
                    'customer_id' => $mandate->stripe_customer_id ?? null,
                    'trace' => $e->getTraceAsString()
                ]);
                $failureCount++;
            }
        }

        Log::info('Bulk charge process completed', [
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ]);

        return redirect()->back()->with('success', "Bulk payment completed. Success: $successCount, Failed: $failureCount");
    }

    public function edit(SepaMandate $mandate)
    {
        return view('sepa.edit', compact('mandate'));
    }

    public function update(Request $request, SepaMandate $mandate)
    {
        try {
            $request->validate([
                'reference' => 'required|unique:sepa_mandates,reference,' . $mandate->id,
                'customer_name' => 'required',
                'customer_email' => 'required|email',
                'iban' => 'required',
                'amount' => 'required|numeric|min:0',
                'payment_status' => 'required|in:not_charged,requires_payment_method,requires_confirmation,processing,succeeded,failed,canceled',
            ]);

            // Update Stripe Customer
            $customer = \Stripe\Customer::update($mandate->stripe_customer_id, [
                'email' => $request->customer_email,
                'name' => $request->customer_name,
            ]);

            // Update SEPA Payment Method
            $paymentMethod = \Stripe\PaymentMethod::retrieve($mandate->stripe_payment_method_id);
            $paymentMethod->update($mandate->stripe_payment_method_id, [
                'billing_details' => [
                    'name' => $request->customer_name,
                    'email' => $request->customer_email,
                ],
            ]);

            // Update local mandate
            $mandate->update([
                'reference' => $request->reference,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'iban' => $request->iban,
                'bic' => $request->bic,
                'amount' => $request->amount,
                'payment_status' => $request->payment_status,
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'SEPA mandate updated successfully'
                ]);
            }

            return redirect()->route('sepa.index')->with('success', 'SEPA mandate updated successfully');
        } catch (\Exception $e) {
            Log::error('Update Error: ' . $e->getMessage(), [
                'mandate_id' => $mandate->id,
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Update failed: ' . $e->getMessage()
                ], 422);
            }

            return redirect()->back()->with('error', 'Update failed: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(SepaMandate $mandate)
    {
        try {
            Log::info('Starting delete process', [
                'mandate_id' => $mandate->id,
                'stripe_customer_id' => $mandate->stripe_customer_id,
                'stripe_payment_method_id' => $mandate->stripe_payment_method_id
            ]);

            // Delete from Stripe
            if ($mandate->stripe_payment_method_id) {
                try {
                    PaymentMethod::retrieve($mandate->stripe_payment_method_id)->detach();
                    Log::info('Detached payment method', [
                        'payment_method_id' => $mandate->stripe_payment_method_id
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to detach payment method: ' . $e->getMessage());
                }
            }

            if ($mandate->stripe_customer_id) {
                try {
                    $customer = Customer::retrieve($mandate->stripe_customer_id);
                    $customer->delete();
                    Log::info('Deleted Stripe customer', [
                        'customer_id' => $mandate->stripe_customer_id
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete Stripe customer: ' . $e->getMessage());
                }
            }

            // Delete from database
            $mandate->delete();
            Log::info('Deleted mandate from database', [
                'mandate_id' => $mandate->id
            ]);

            return redirect()->route('sepa.index')->with('success', 'SEPA mandate deleted successfully');
        } catch (\Exception $e) {
            Log::error('Delete Error: ' . $e->getMessage(), [
                'mandate_id' => $mandate->id,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }
}
