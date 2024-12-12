<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEPA Direct Debit Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-gray-900">SEPA Direct Debit Management</h1>
                    </div>
                    <div class="flex items-center">
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Import Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Import SEPA Mandates</h2>
                
                <div class="flex items-center space-x-4 mb-4">
                    <a href="{{ route('sepa.template') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-download mr-2"></i>
                        Download Template
                    </a>
                    <span class="text-sm text-gray-600">Download the CSV template to see the required format</span>
                </div>

                <form action="{{ route('sepa.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Upload Excel/CSV File</label>
                        <div class="mt-1 flex items-center space-x-4">
                            <input type="file" name="file" accept=".xlsx,.csv" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-upload mr-2"></i>
                                Import Mandates
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Bulk Actions</h2>
                <form action="{{ route('sepa.charge-all') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                        <i class="fas fa-bolt mr-2"></i>
                        Charge All Pending Payments
                    </button>
                </form>
            </div>

            <!-- Mandates Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IBAN</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Payment</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Payment</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Signed Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($mandates as $mandate)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $mandate->reference }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $mandate->customer_name }}</div>
                                    <div class="text-sm text-gray-500">{{ $mandate->customer_email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $mandate->iban }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ number_format($mandate->amount, 2) }} {{ $mandate->currency }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $mandate->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $mandate->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $mandate->payment_status === 'succeeded' ? 'bg-green-100 text-green-800' : 
                                           ($mandate->payment_status === 'processing' ? 'bg-blue-100 text-blue-800' : 
                                           ($mandate->payment_status === 'requires_payment_method' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                        {{ $mandate->payment_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $mandate->last_payment_date ? $mandate->last_payment_date->format('Y-m-d H:i') : 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $mandate->next_payment_date ? $mandate->next_payment_date->format('Y-m-d') : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $mandate->signed_date ? $mandate->signed_date->format('Y-m-d') : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <div class="flex items-center space-x-2">
                                        @if($mandate->status === 'active' && $mandate->payment_status !== 'succeeded')
                                        <form action="{{ route('sepa.charge', $mandate) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-credit-card mr-1"></i>
                                                Charge
                                            </button>
                                        </form>
                                        @endif
                                        
                                        <button onclick="openEditModal({{ json_encode($mandate) }})" class="inline-flex items-center px-3 py-1 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                            <i class="fas fa-edit mr-1"></i>
                                            Edit
                                        </button>
                                        
                                        <form action="{{ route('sepa.destroy', $mandate) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this mandate?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center px-3 py-1 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                <i class="fas fa-trash-alt mr-1"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $mandates->links() }}
                </div>
            </div>

            <!-- Edit Modal -->
            <div id="editModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden" aria-hidden="true">
                <div class="flex items-center justify-center min-h-screen">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 overflow-hidden">
                        <div class="px-6 py-4 bg-gray-100 border-b flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Edit SEPA Mandate</h3>
                            <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form id="editForm" method="POST" class="p-6 space-y-6">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="modal_reference" class="block text-sm font-medium text-gray-700">Reference</label>
                                    <input type="text" name="reference" id="modal_reference" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label for="modal_customer_name" class="block text-sm font-medium text-gray-700">Customer Name</label>
                                    <input type="text" name="customer_name" id="modal_customer_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label for="modal_customer_email" class="block text-sm font-medium text-gray-700">Customer Email</label>
                                    <input type="email" name="customer_email" id="modal_customer_email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label for="modal_iban" class="block text-sm font-medium text-gray-700">IBAN</label>
                                    <input type="text" name="iban" id="modal_iban" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label for="modal_bic" class="block text-sm font-medium text-gray-700">BIC</label>
                                    <input type="text" name="bic" id="modal_bic" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label for="modal_amount" class="block text-sm font-medium text-gray-700">Amount</label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="number" name="amount" id="modal_amount" step="0.01" class="block w-full rounded-md border-gray-300 pl-3 pr-12 focus:border-blue-500 focus:ring-blue-500">
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                            <span class="text-gray-500 sm:text-sm" id="modal_currency">EUR</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="modal_payment_status" class="block text-sm font-medium text-gray-700">Payment Status</label>
                                    <select name="payment_status" id="modal_payment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="not_charged">Not Charged</option>
                                        <option value="requires_payment_method">Requires Payment Method</option>
                                        <option value="requires_confirmation">Requires Confirmation</option>
                                        <option value="processing">Processing</option>
                                        <option value="succeeded">Succeeded</option>
                                        <option value="failed">Failed</option>
                                        <option value="canceled">Canceled</option>
                                    </select>
                                </div>

                            </div>

                            <div class="flex justify-end space-x-3 pt-6 border-t">
                                <button type="button" onclick="closeModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Cancel
                                </button>
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-save mr-2"></i>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                function openEditModal(mandate) {
                    // Set form action URL
                    document.getElementById('editForm').action = `/sepa/${mandate.id}`;
                    
                    // Fill form fields
                    document.getElementById('modal_reference').value = mandate.reference;
                    document.getElementById('modal_customer_name').value = mandate.customer_name;
                    document.getElementById('modal_customer_email').value = mandate.customer_email;
                    document.getElementById('modal_iban').value = mandate.iban;
                    document.getElementById('modal_bic').value = mandate.bic;
                    document.getElementById('modal_amount').value = mandate.amount;
                    document.getElementById('modal_currency').textContent = mandate.currency;
                    document.getElementById('modal_payment_status').value = mandate.payment_status;
                    
                    // Show modal
                    document.getElementById('editModal').classList.remove('hidden');
                }

                function closeModal() {
                    document.getElementById('editModal').classList.add('hidden');
                }

                // Close modal when clicking outside
                document.getElementById('editModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });

                // Handle form submission with AJAX
                document.getElementById('editForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    fetch(this.action, {
                        method: 'POST',
                        body: new FormData(this),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'An error occurred while saving the changes.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while saving the changes.');
                    });
                });
            </script>
        </div>
    </div>
</body>
</html> 