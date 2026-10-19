<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\qrcodev2Controller;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\Smart_QRCodeController;
use App\Http\Controllers\smartqrcodev2Controller;
use App\Http\Controllers\UserTransactionController;
use App\Http\Middleware\CheckAdminRole;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserLocationController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckSEORole;

###########################################################USER_AUTH########################################################################################
Route::get('/user_info', [AuthController::class, 'getUserData'])->middleware('auth:sanctum');
Route::put('/user_info/update', [UserController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/admin/user_info/delete/{id}', [UserController::class, 'deleteUser'])->middleware(['auth:sanctum', CheckAdminRole::class]);

Route::post('/admin/signup', [AuthController::class, 'Admin_signup']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forget-password', [AuthController::class, 'forgetPassword']);


Route::post('/testmail', [AuthController::class, 'sendMail']);

Route::post('/signup', [AuthController::class, 'sendVerificationCode']);
// Route::get('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
Route::get('/count_user&qr', [AuthController::class, 'count']);






Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleSignUpOrLogin']);

###########################################################QR-CODE########################################################################################

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/generate-qrcode', [QRCodeController::class, 'generateQRCode']);
    Route::post('/generate-qrcode/whatsapp', [QRCodeController::class, 'generateWhatsappQrCode']);
    Route::post('/generate-pdf-qrcode', [QrCodeController::class, 'generatePdfQrCode']);
    Route::post('/generate-wifi-qrcode', [QrCodeController::class, 'generatewifiQrCode']);
});

Route::get('/scan_qrcode/{name}', [QrCodeController::class, 'trackAndRedirect']);



Route::middleware('auth:sanctum')->get('/user/qrcode', [QrCodeController::class, 'getQrcodeByUserId']);


Route::post('/generate-qrcode/whatsapp', [QRCodeController::class, 'generateWhatsappQrCode']);

Route::get('/qrcodes/{id}/check-visitor-count', [QRCodeController::class, 'checkVisitorCount']);

###########################################################USER_Dashboard"QR_CODE"########################################################################################

Route::get('/qrcodes/{user_id}', [Smart_QRCodeController::class, 'getQRCodesByUserId']);
Route::delete('/qrcode/{id}', [Smart_QRCodeController::class, 'deleteQRCodeById']);

###########################################################Smart_QRCode########################################################################################
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/qrcode/smart', [Smart_QRCodeController::class, 'generatesmartQRCodev2']); ///unused
    Route::post('/qrcode/smart/v3', [smartqrcodev2Controller::class, 'generatesmartQRCodev3']);
    Route::get('/track-qr-code/{name}', [QrCodeController::class, 'trackAndRedirectAPI']);
});

Route::middleware('auth:sanctum')->post('/track-qr-code/{id}', [QrCodeController::class, 'trackQRCode']);
#********************************************************qr-code v2*************************************************************************************************

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/profile', [qrcodev2Controller::class, 'saveProfileData']);
    Route::post('/qr-code/{profile_id}', [qrcodev2Controller::class, 'generateQRCodeByProfileId']);
});


#********************************************************USER_PROFILE*************************************************************************************************


Route::get('/profiles/{user_id}', [UserProfileController::class, 'getAllProfilesByUserId']);
Route::get('/profile/{id}', [UserProfileController::class, 'getProfileById']);
Route::get('/profile/qrcode/{qrCodeName}', [UserProfileController::class, 'getProfileByQRCodeName']);
Route::post('/profile/{id}', [UserProfileController::class, 'updateProfile'])->middleware('auth:sanctum');

#********************************************************USER_location*************************************************************************************************



Route::get('user-locations/{user_id}/{qrcode_id}', [UserLocationController::class, 'getUserLocationByUserIdAndQRCodeId']);
Route::post('/track/{id}', [UserLocationController::class, 'trackQRCode']);
###########################################################Package########################################################################################




Route::post('/packages', [PackageController::class, 'store']);  // Add a package
Route::get('/packages', [PackageController::class, 'index']);   // Get all packages
Route::get('/packages/{id}', [PackageController::class, 'show']);  // Get a specific package
Route::put('/packages/{id}', [PackageController::class, 'update']); // Update a package
Route::delete('/packages/{id}', [PackageController::class, 'destroy']); // Delete a package



###########################################################USER_SUBSCRIPTION########################################################################################
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/subscriptions', [SubscriptionController::class, 'store']); //create subscription
    Route::get('/subscriptions/validate', [SubscriptionController::class, 'validateUserSubscription']);    // Validate and disable expired subscriptions and related qrcode
    Route::get('/subscriptions/user', [SubscriptionController::class, 'getByUserId']);
    ###########################################################UPGRADE######################################################################################################
    Route::post('/Upgrade-QR-Duration', [SubscriptionController::class, 'updateSubscriptionDurationv2']);    //renew  packagee
    Route::post('/Upgrade-QRlimit', [SubscriptionController::class, 'updateQrCodeLimit']);
    Route::post('/Upgrade-package', [SubscriptionController::class, 'renewUserPackage']);
    Route::post('/new_price', [SubscriptionController::class, 'price_upgrade']); //The price when user upgrade to package & his duration still active
    Route::post('/new_price/maxqr', [SubscriptionController::class, 'price_qr']); //The price is based on the number of QR codes the user has beyond the default (2 QR codes) *in case of renew his duration *
    Route::post('/create-payment-link', [PaymentController::class, 'createPaymentLink']);
});

Route::middleware('auth:sanctum')->get('/check-subscription-status/{id}', [SubscriptionController::class, 'checkSubscriptionStatus']);



// Get subscriptions by user ID
Route::get('/subscriptions/package/{packageId}', [SubscriptionController::class, 'getByPackageId']);  // Get subscriptions by package ID    //for admin

###########################################################GEIDEA_PAYMENT########################################################################################
Route::match(['GET', 'POST'], '/payment/callback', [PaymentController::class, 'handleCallback']);

Route::post('/payment/initiate', [PaymentController::class, 'initializePayment']);


Route::post('/send-money', [PaymentController::class, 'sendMoney']);
// Route::post('/payment-callback', [PaymentController::class, 'handleCallback'])->name('geidea.callback');
###########################################################transaction########################################################################################
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/transactions', [UserTransactionController::class, 'store']);
    Route::get('/transactions/user', [UserTransactionController::class, 'getByUser']);

});

Route::get('/transactions', [UserTransactionController::class, 'getAll']);

###########################################################storage_link########################################################################################
Route::get('/link', function () {
    try {

        Artisan::call('storage:link');


        return response()->json(['message' => 'Storage linked successfully.'], 200);
    } catch (\Exception $e) {

        return response()->json(['message' => 'Failed to link storage.', 'error' => $e->getMessage()], 500);
    }
});
###########################################################code########################################################################################
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/codes/validate', [CodeController::class, 'validateCode']);
    Route::get('/code/check/{package_id}', [CodeController::class, 'checkUserCodeStatus']);
});


Route::post('/addcode/{package_id}', [CodeController::class, 'store']);










Route::post('/records', [RecordController::class, 'store']);


###########################################################contact-us########################################################################################

Route::post('/contact-us', [ContactUsController::class, 'store']);

###########################################################ADMIN-Dashboard########################################################################################
Route::middleware(['auth:sanctum',CheckAdminRole::class])->group(function () {

Route::get('/admin/users-with-packages', [AdminDashboardController::class, 'getAllUsersWithPackages']);
Route::get('/admin/users-with-qrcodes', [AdminDashboardController::class, 'getEachUserWithQrCodes']);
Route::get('/qrcode-stats', [AdminDashboardController::class, 'getQrCodeStats']);
});
//*take care* that this api delete qrcode and all associated data related to the profile_id
Route::delete('/Delete_qrcode/{id}', [AdminDashboardController::class, 'deleteQrCodeAndProfile']);

###########################################################ADMIN_BLOGS-Dashboard########################################################################################
Route::middleware(['auth:sanctum',CheckSEORole::class])->group(function () {
Route::post('/blogs', [BlogController::class, 'store']);
Route::delete('/blogs/{id}', [BlogController::class, 'delete']);
});
Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{slug}', [BlogController::class, 'show']);
