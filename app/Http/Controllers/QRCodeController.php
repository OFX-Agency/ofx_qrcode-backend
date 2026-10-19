<?php

namespace App\Http\Controllers;

use App\Models\pdfs;
use App\Models\UserLocation;
use App\Models\WhatsappMessage;
use App\Models\Wifi;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use App\Models\QrCodeModel;
use Stevebauman\Location\Facades\Location;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class QRCodeController extends Controller
{

    ////////generate qr code for links direct

    public function generateQrCode(Request $request)
    {
        // Validate incoming data
        $validatedData = $request->validate([
            'link' => 'required',
            'package_id' => 'required',
        ]);

        $user = $request->user();


        $packageId = $request->input('package_id');
        $userPackage = $user->packages()->first(); // Assuming `package` is the relationship
        // log::info('userinfo', $userPackage);
        if ( $userPackage->pivot->package_id == 1) {
            // Check if the user has exceeded the scan count limit
            $userQrCodeWithMaxScan = QrCodeModel::where('user_id', $user->id)
                ->where('scan_count', '>=', 20)
                ->exists();

            // If the user has exceeded the limit, return an error response
            if ($userQrCodeWithMaxScan) {
                return response()->json([
                    'message' => 'You have reached the maximum scan limit of 20 for one of your QR codes.',
                ], 400);
            }
        }
        $link = $validatedData['link'];

        // Create new QR Code Model entry
        $qrCodeModel = new QrCodeModel();
        $qrCodeModel->link = $link;
        $qrCodeModel->type = 'link';
        $qrCodeModel->profile_id = null;
        $qrCodeModel->user_id = $user->id;
        $qrCodeModel->package_id = $validatedData['package_id'];
        $qrCodeModel->save(); // Save model to generate ID

        // Generate a unique name for the QR code
        $uniqueName = uniqid();


        $fileName = 'qrcodes/' . $uniqueName . '.png';


        $qrCode = QrCode::format('png')
            ->backgroundColor(255, 255, 255)
            ->size(200)
            ->color(0, 0, 0)
            ->generate(route('qrcode.scan', ['name' => $uniqueName]));

        // Store the QR code file
        Storage::disk('public')->put($fileName, $qrCode);

        // Update the model with the file path
        $qrCodeModel->qrcode = $fileName;
        $qrCodeModel->save(); // Save again after storing the file

        // Generate the tracking link using the unique name
        $trackingLink = route('qrcode.scan', ['name' => $uniqueName]);

        // Return the QR code URL and the tracking link
        return response()->json([
            'qr_code_url' => Storage::url($fileName),
            'tracking_link' => $trackingLink,
        ]);
    }



    public function generatewifiQrCode(Request $request)
    {
        // Validate incoming data
        $validatedData = $request->validate([
            'package_id' => 'required',
            'name' => 'required|string', // Wi-Fi name
            'password' => 'required|string', // Wi-Fi password
            'encryption' => 'required|string|in:WEP,WPA,WPA2', // Wi-Fi encryption type
        ]);

        $user = $request->user();
        $name = $validatedData['name'];
        $password = $validatedData['password'];
        $encryption = $validatedData['encryption'];

        // Create new QR Code Model entry
        $qrCodeModel = new QrCodeModel();
        $qrCodeModel->link = null; // No link for Wi-Fi
        $qrCodeModel->type = 'wifi';
        $qrCodeModel->profile_id = null;
        $qrCodeModel->user_id = $user->id;
        $qrCodeModel->package_id = $validatedData['package_id'];
        $qrCodeModel->save(); // Save model to generate ID

        // Save Wi-Fi data in the wifi table
        $wifiModel = new Wifi();
        $wifiModel->qrcode_id = $qrCodeModel->id; // Link the QR code to the Wi-Fi data
        $wifiModel->name = $name;
        $wifiModel->password = $password;
        $wifiModel->encryption = $encryption;
        $wifiModel->save(); // Save Wi-Fi data
        $wifiLink = route('wifi.details', [
            'name' => urlencode($name),
            'password' => urlencode($password),
            'encryption' => urlencode($encryption),
        ]);
        // Update the QR Code Model with the Wi-Fi link
        $qrCodeModel->link = $wifiLink; // Save the generated link in the QR Code Model
        $qrCodeModel->save(); // Save again after updating the link

        // Generate QR Code for Wi-Fi details
        $qrCodeData = "WIFI:S:{$name};T:{$encryption};P:{$password};;";

        // Generate a unique name for the QR code
        $uniqueName = uniqid();
        $fileName = 'qrcodes/' . $uniqueName . '.png';

        // Generate the QR code
        $qrCode = QrCode::format('png')
            ->backgroundColor(255, 255, 255)
            ->size(200)
            ->color(0, 0, 0)
            ->generate($qrCodeData);

        // Store the QR code file
        Storage::disk('public')->put($fileName, $qrCode);

        // Update the model with the file path
        $qrCodeModel->qrcode = $fileName;
        $qrCodeModel->save(); // Save again after storing the file

        // Generate the tracking link using the unique name
        $trackingLink = route('qrcode.scan', ['name' => $uniqueName]);

        // Return the QR code URL and the tracking link
        return response()->json([
            'qr_code_url' => Storage::url($fileName),
            'tracking_link' => $trackingLink,
            'wifi_link' => $wifiLink,
            'message' => 'Wi-Fi QR code generated and saved successfully.',
        ]);
    }


    public function generatePdfQrCode(Request $request)
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:2048', // PDF file up to 2MB
            'package_id' => 'required|integer',
        ]);

        // $user = $request->user();

        // Store the PDF file in 'pdfs/' directory
        $pdfFile = $request->file('pdf');
        $pdfFileName = 'pdfs/' . uniqid() . '.' . $pdfFile->getClientOriginalExtension();
        $pdfFilePath = $pdfFile->storeAs('public', $pdfFileName);

        // Generate a full URL for the stored PDF
        $pdfUrl = Storage::url($pdfFileName);

        // Create new QR Code Model entry
        $qrCodeModel = new QrCodeModel();
        $qrCodeModel->user_id = '1';
        $qrCodeModel->link = $pdfUrl; // Store full URL in the link field
        $qrCodeModel->type = 'pdf';
        $qrCodeModel->package_id = $validatedData['package_id'];
        $qrCodeModel->save(); // Save to generate ID

        // Generate a unique name for the QR code
        $uniqueName = uniqid();
        $qrCodeFileName = 'qrcodes/' . $uniqueName . '.png';

        // Generate the QR code linking to the PDF URL
        $qrCode = QrCode::format('png')
            ->backgroundColor(255, 255, 255)
            ->size(200)
            ->color(0, 0, 0)
            ->generate($pdfUrl);

        // Store the QR code image in 'qrcodes/' directory
        Storage::disk('public')->put($qrCodeFileName, $qrCode);

        // Update the QR code model with the QR code file path
        $qrCodeModel->qrcode = $qrCodeFileName;
        $qrCodeModel->save();

        // Create new PDF entry and link it to the QR code
        $pdf = new pdfs();
        $pdf->pdf_path = $pdfFileName; // Store only the relative path
        $pdf->qrcode_id = $qrCodeModel->id;
        $pdf->save();

        // Return the PDF and QR code URLs
        return response()->json([
            'pdf_url' => $pdfUrl,
            'qr_code_url' => Storage::url($qrCodeFileName),
        ]);
    }






    public function generateWhatsappQrCode(Request $request)
    {
        // Validate incoming data
        $validatedData = $request->validate([
            'phone_number' => 'required|string',
            'message' => 'required|string',
            'package_id' => 'required',
        ]);

        $user = $request->user();
        $phoneNumber = $validatedData['phone_number'];
        $message = urlencode($validatedData['message']);
        $link = "https://wa.me/$phoneNumber?text=$message";

        // Create new QR Code Model entry
        $qrCodeModel = new QrCodeModel();
        $qrCodeModel->link = $link;
        $qrCodeModel->type = 'whatsapp';
        $qrCodeModel->profile_id = null;
        $qrCodeModel->user_id = $user->id;
        $qrCodeModel->package_id = $validatedData['package_id'];
        $qrCodeModel->save(); // Save model to generate ID

        // Create new WhatsApp message entry
        $whatsappMessage = new WhatsappMessage();
        $whatsappMessage->phone_number = $phoneNumber;
        $whatsappMessage->message = $validatedData['message'];
        $whatsappMessage->qr_code_id = $qrCodeModel->id;
        $whatsappMessage->save();

        // Generate a unique name for the QR code
        $uniqueName = uniqid();
        $fileName = 'qrcodes/' . $uniqueName . '.png';

        // Generate the QR code image
        $qrCode = QrCode::format('png')
            ->backgroundColor(255, 255, 255)
            ->size(200)
            ->color(0, 0, 0)
            ->generate($link);

        // Store the QR code file
        Storage::disk('public')->put($fileName, $qrCode);

        // Update the model with the file path
        $qrCodeModel->qrcode = $fileName;
        $qrCodeModel->save(); // Save again after storing the file

        // Return the QR code URL and the WhatsApp link
        return response()->json([
            'qr_code_url' => Storage::url($fileName),
            'whatsapp_link' => $link,
        ]);
    }






    public function trackAndRedirectweb($name, Request $request)
    {
        $qrCodeModel = QrCodeModel::where('qrcode', 'qrcodes/' . $name.'.png')->first();

            if (!$qrCodeModel || $qrCodeModel->is_active == 0) {
                abort(404, 'QR code not found or is inactive.');
            }

        if ($qrCodeModel->checkVisitorCount($qrCodeModel->scan_count, $qrCodeModel->package_id)) {
            // Increment the scan count only if within limits
            $qrCodeModel->increment('scan_count'); // Laravel's increment method

            $userLocation = Location::get($request->ip());
            if ($userLocation) {
                // Prepare location data
                $locationData = [
                    'ip' => $userLocation->ip ?? 'N/A',
                    'country' => $userLocation->countryName ?? 'N/A',
                    'city' => $userLocation->cityName ?? 'N/A',
                    'latitude' => $userLocation->latitude ?? null,
                    'longitude' => $userLocation->longitude ?? null,
                ];

                // Save location data to user_location table
                UserLocation::create([
                    'qrcode_id' => $qrCodeModel->id, // Link to the QR code
                    'location' => json_encode($locationData), // Store location as JSON
                ]);

                // Log the location data for debugging
                Log::info('User Location saved:', $locationData);
            }

            return redirect($qrCodeModel->link);
        } else {
            // Deactivate the QR code and abort with a 404
            $qrCodeModel->update(['is_active' => 0]);
            abort(404);
        }
    }












    // QrCodeController.php
    public function trackAndRedirect($name, Request $request)
    {
        $qrCodeModel = QrCodeModel::where('link', 'https://ofx-qrcode.com/qr/' . $name)->first();

            if (!$qrCodeModel || $qrCodeModel->is_active == 0) {
                abort(404, 'QR code not found or is inactive.');
            }

        if ($qrCodeModel->checkVisitorCount($qrCodeModel->scan_count, $qrCodeModel->package_id)) {
            // Increment the scan count only if within limits
            $qrCodeModel->increment('scan_count'); // Laravel's increment method

            $userLocation = Location::get($request->ip());
            if ($userLocation) {
                // Prepare location data
                $locationData = [
                    'ip' => $userLocation->ip ?? 'N/A',
                    'country' => $userLocation->countryName ?? 'N/A',
                    'city' => $userLocation->cityName ?? 'N/A',
                    'latitude' => $userLocation->latitude ?? null,
                    'longitude' => $userLocation->longitude ?? null,
                ];

                // Save location data to user_location table
                UserLocation::create([
                    'qrcode_id' => $qrCodeModel->id, // Link to the QR code
                    'location' => json_encode($locationData), // Store location as JSON
                ]);

                // Log the location data for debugging
                Log::info('User Location saved:', $locationData);
            }

            return redirect($qrCodeModel->link);
        } else {
            // Deactivate the QR code and abort with a 404
            $qrCodeModel->update(['is_active' => 0]);
            abort(404);
        }
    }


    public function trackAndRedirectAPI($name, Request $request)
    {

        $user = $request->user();
        // Find the QR code by its link
        $qrCodeModel = QrCodeModel::where('link', 'https://ofx-qrcode.com/qr/' . $name)->first();

        // Check if the QR code exists
        if (!$qrCodeModel) {
            abort(404, 'QR Code not found');
        }

        // Verify the scan count limit with the checkVisitorCount function
        if ($qrCodeModel->checkVisitorCount($qrCodeModel->scan_count, $qrCodeModel->package_id)) {
            // Increment the scan count
            $qrCodeModel->increment('scan_count');

            // Retrieve the user’s location based on IP
            $userLocation = Location::get($request->ip());
            if ($userLocation) {
                // Prepare location data
                $locationData = [
                    'ip' => $userLocation->ip ?? 'N/A',
                    'country' => $userLocation->countryName ?? 'N/A',
                    'city' => $userLocation->cityName ?? 'N/A',
                    'latitude' => $userLocation->latitude ?? null,
                    'longitude' => $userLocation->longitude ?? null,
                ];

                // Save location data to the user_location table
                UserLocation::create([
                    'user_id' => $user->id,
                    'qrcode_id' => $qrCodeModel->id, // Link to the QR code
                    'location' => json_encode($locationData), // Store location as JSON
                ]);

                // Log location data for debugging
                Log::info('User Location saved:', $locationData);
            }

            // Redirect to the QR code's link
            return redirect($qrCodeModel->link);
        } else {
            // If scan count exceeds limit, deactivate the QR code and return a 404 error
            $qrCodeModel->update(['is_active' => 0]);
            abort(404, 'QR Code is inactive');
        }
    }


    public function trackAndRedirectAPIv2($name, Request $request)
{
    $user = $request->user();
    // Find the QR code by its link
    $qrCodeModel = QrCodeModel::where('link', 'https://ofx-qrcode.com/qr/' . $name)->first();

    // Check if the QR code exists
    if (!$qrCodeModel) {
        abort(404, 'QR Code not found');
    }

    $userPackage = $user->packages()->where('package_id', $qrCodeModel->package_id)->first();

    // If the user does not have the package associated with the QR code, return an error
    if (!$userPackage) {
        return response()->json([
            'message' => 'User does not have the associated package for this QR code.',
        ], 400);
    }

    // Get the qrcode_limit for the user's package
    $qrcodeLimit = $userPackage->pivot->qrcode_limit;

    // Count how many QR codes the user has created for this package
    $userProfileCount = QrCodeModel::where('user_id', $user->id)
        ->where('package_id', $qrCodeModel->package_id)
        ->count();

    // Check if the user has reached the QR code limit for the selected package
    if ($userProfileCount >= $qrcodeLimit) {
        // Deactivate the QR code if the limit is reached
        $qrCodeModel->update(['is_active' => 0]);
        abort(404, 'QR Code is inactive');
        // Return a message about the profile limit being reached
        return response()->json([
            'message' => "You have reached the maximum profile limit of QR codes for this package.",
        ], 400);
    }

    // Verify the scan count limit with the checkVisitorCount function
    if ($qrCodeModel->checkVisitorCount($qrCodeModel->scan_count, $qrCodeModel->package_id)) {
        // Increment the scan count
        $qrCodeModel->increment('scan_count');

        // Retrieve the user’s location based on IP
        $userLocation = Location::get($request->ip());
        if ($userLocation) {
            // Prepare location data
            $locationData = [
                'ip' => $userLocation->ip ?? 'N/A',
                'country' => $userLocation->countryName ?? 'N/A',
                'city' => $userLocation->cityName ?? 'N/A',
                'latitude' => $userLocation->latitude ?? null,
                'longitude' => $userLocation->longitude ?? null,
            ];

            // Save location data to the user_location table
            UserLocation::create([
                'user_id' => $user->id,
                'qrcode_id' => $qrCodeModel->id, // Link to the QR code
                'location' => json_encode($locationData), // Store location as JSON
            ]);

            // Log location data for debugging
            Log::info('User Location saved:', $locationData);
        }

        // Redirect to the QR code's link
        return redirect($qrCodeModel->link);
    } else {
        // If scan count exceeds limit, deactivate the QR code and return a 404 error
        $qrCodeModel->update(['is_active' => 0]);
        abort(404, 'QR Code is inactive');
    }
}






    public function trackQRCode($id, Request $request)
    {
        // Find the QR code by ID
        $qrCodeModel = QrCodeModel::findOrFail($id);

        $user = $request->user();
        // Increment the scan count
        $qrCodeModel->scans_count += 1;
        $qrCodeModel->save();

        // Get the user's IP address
        $ipAddress = $request->ip();

        // Use a location service to get the user's location based on the IP
        $userLocation = Location::get($ipAddress);

        if ($userLocation) {
            // Check if userLocation has the expected properties
            if (isset($userLocation->ip) && isset($userLocation->countryName) && isset($userLocation->cityName)) {
                // Prepare location data array
                $locationData = [
                    'ip' => $userLocation->ip,
                    'country' => $userLocation->countryName,
                    'city' => $userLocation->cityName,
                    'latitude' => $userLocation->latitude,
                    'longitude' => $userLocation->longitude,
                ];

                // Convert location data to JSON format
                $locationJson = json_encode($locationData);

                // Save location data to the qr_code_model
                $qrCodeModel->user_location = $locationJson;
                $qrCodeModel->save();

                // Save location data to the user_location table
                UserLocation::create([
                    'user_id' => $user->id, // Assuming the user is authenticated
                    'qrcode_id' => $qrCodeModel->id, // Reference to the QR code
                    'location' => $locationJson, // Store location as JSON
                ]);

                // Log the location data
                Log::info('User Location saved:', $locationData);
            }
        }

        // Return a response with scan count, location, and IP
        return response()->json([
            'message' => 'QR code scan tracked successfully.',
            'qr_code_id' => $qrCodeModel->id,
            'scan_count' => $qrCodeModel->scans_count,
            'location' => $userLocation ? $locationJson : 'Location not found',
            'ip_address' => $ipAddress,
        ], 200);
    }





    public function getQrcodeByUserIdv(Request $request)
    {
        // Retrieve the authenticated user
        $user = $request->user();

        try {
            // Attempt to find the QR code associated with the user's ID
            $qrCodeModel = QrCodeModel::where('user_id', $user->id)->get();
            return response()->json($qrCodeModel, 200);
        } catch (ModelNotFoundException $e) {
            // Handle the case when no QR code is found
            return response()->json([
                'message' => 'QR code not found for this user.'
            ], 404);
        }
    }




    public function getQrcodeByUserId(Request $request)
    {
        $qrCodeModels = QrCodeModel::where('user_id', $request->user()->id)
            ->with('UserLocation', 'profile')
            ->get();

        $qrCodeData = $qrCodeModels->map(function (QrCodeModel $qrCode) {
            $scanData = $qrCode->UserLocation
                ->map(function (UserLocation $scan) {
                    $location = json_decode($scan->location, true);

                    return is_array($location) ? $location : null;
                })
                ->filter();

            $locationData = $scanData
                ->unique(fn (array $location) => implode('|', [
                    $location['ip'] ?? '',
                    $location['country'] ?? '',
                    $location['city'] ?? '',
                ]))
                ->map(fn (array $location) => [
                    'country' => $location['country'] ?? null,
                    'city' => $location['city'] ?? null,
                ])
                ->values();

            return [
                'qr_code' => $qrCode,
                'locations' => $locationData,
                'device_count' => $scanData
                    ->pluck('ip')
                    ->filter()
                    ->unique()
                    ->count(),
            ];
        });

        return response()->json([
            'qr_codes' => $qrCodeData,
        ], 200);
    }
    // public function checkVisitorCount( $id)
    // {
    //     $qrcode = QrCodeModel::findOrFail($id);

    //     // Check and deactivate if conditions are met
    //     if ($qrcode->checkVisitorCount()) {
    //         return response()->json(['message' => 'QR code deactivated due to maximum visitor count reached'], 200);
    //     }

    //     return response()->json(['message' => 'QR code is still active'], 200);
    // }










}
