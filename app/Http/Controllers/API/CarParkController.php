<?php

namespace App\Http\Controllers\API;

use Exception;
use Geocoder;
use App\CarPark;
use App\User;
use App\Classes\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class CarParkController extends Controller
{
    /**
     * @var string $user
     * @access protected
     */
    protected $user;

    /**
     * Gets authenticated user's data
     *
     * @return App\User
     */
    public function __construct()
    {
        $this->user = auth()->user();
    }

    /**
     * Add a parking space to the database
     *
     * @return \Illuminate\Http\Response
     */
    public function store(
        CarPark $park,
        Request $request,
        ImageController $image
    ){
        // Validate posted data
        $this->validate($request, [
            'name'        => ['bail', 'required', 'regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/'],
            'owner'       => ['bail', 'required', 'string',],
            'address'     => ['bail', 'required', 'string',],
            'phone'       => ['bail', 'required', 'string', 'min:11', 'phone:NG'],
            'fee'         => ['bail', 'required', 'between:0,99.99', 'min:0'],
        ]);

//        $client = new \GuzzleHttp\Client();
//
//        $geocoder = new Geocoder($client);
//
//        $geocoder->setApiKey(config('geocoder.key'));
//
//        $geocoder->setCountry(config('NG'));

        $location = Geocoder::getCoordinatesForAddress($request->address);


        $park = new CarPark;

        $park->name       = $request->name;
        $park->owner      = $request->owner;
        $park->address    = $request->address;
        $park->phone      = $request->phone;
        $park->fee        = $request->fee;
        $park->user_id    = $this->user->id;
        $park->status     = 1;

        // Upload image
        if ($request->hasFile('image')) {
            $data = $this->upload($request, $image);

            if ($data['code'] !=  200) {
                return response()->json($data, $data['code']);
            }
            else {
                $park->image_name = $data['image'];
                $park->image_link = $data['image_link'];
            }
        }
        else {
            $data = null;
            $park->image_name = 'no-image.png';
            $park->image_link = '';
        }

        // Save to db
        if ($park->save()) {
            // Prepare JSON response
            $image_data = [
                'image_round_format' => $data['image_round_format'],
                'image_square_format' => $data['image_square_format'],
                'image_link' => $data['image_link']
            ];

            $park_details = [
                'park_id' => $park->id,
                'name' => $park->name,
                'owner' => $park->owner,
                'address' => $park->address,
                'phone' => $park->phone,
                'fee' => $park->fee,
                'user_id' => $park->user_id,
            ];

            $result_set = array_merge($park_details, $image_data);
            return response()->json([
                'status'  => true,
                'result'  => [
                    'park' => $park,
                    'location' => [
                        'lat' => $location['lat'],
                        'lng' => $location['lng'],
                    ]
                ],
                'message' => 'Car Park was successfully added',
                'result'  => $result_set,
                'message' => 'The parking space was successfully added'
            ], 200);
        }
        else {
            return response()->json([
                'status'  => false,
                'message' => 'An error occurred'
            ], 501);
        }
    }

   /**
     * Update a parking space record
     *
     * @return \Illuminate\Http\Response
     */
    public function update(
        $id,
        CarPark $update,
        Request $request,
        ImageController $image
    ){
        // Get the car park if the admin user is assigned to it
        $update = $this->user->parks()->find($id);

        // Proceed to update if record exists
        if (!is_null($update)) {
            // Validate posted data
            $this->validate($request, [
                'name'        => ['regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/'],
                'owner'       => ['string',],
                'address'     => ['string',],
                'phone'       => ['string', 'min:11', 'phone:NG'],
                'fee'         => ['integer', 'min:0'],
                'status'      => ['integer'],
            ]);

            $update->name       = $request->name ?? $update->name;
            $update->owner      = $request->owner ?? $update->owner;
            $update->address    = $request->address ?? $update->address;
            $update->phone      = $request->phone ?? $update->phone;
            $update->fee        = $request->fee ?? $update->fee;
            $update->status     = $request->status ?? $update->status;

            // Upload image
            if ($request->hasFile('image')) {
                $data = $this->upload($request, $image);

                if ($data['status_code'] !=  200) {
                    return response()->json($data, $data['status_code']);
                }
                else {
                    $update->image_name = $data['image'];
                    $update->image_link = $data['image_link'];
                }
            }
            else {
                $data = null;
                $data['image_link'] = $update->image_link;
            }

            // Save to db
            if ($update->save()) {
                // Prepare JSON response
                $image_data = [
                    'image_link' => $data['image_link']
                ];

                $park_details = [
                    'park_id' => $update->id,
                    'name' => $update->name,
                    'owner' => $update->owner,
                    'address' => $update->address,
                    'phone' => $update->phone,
                    'fee' => $update->fee,
                    'user_id' => $update->user_id,
                ];

                $result_set = array_merge($park_details, $image_data);

                return response()->json([
                    'status'  => true,
                    'result'  => $result_set,
                    'message' => 'The record was successfully updated'
                ], 200);
            }
            else {
                return response()->json([
                    'status'  => false,
                    'message' => 'An error occurred'
                ], 501);
            }
        }
        else {
            return response()->json([
                'status'  => false,
                'message' => 'Unknown error',
                'hint'    => 'Failed due to inexistent record or insufficient write permission on the record'
            ], 404);
        }
    }

   /**
     * Get all parking spaces
     *
     * This method is recommended for web usage
     * @return \Illuminate\Http\Response
     */
    public function apiIndex(Request $request)
    {
        // return the requested number of parking spaces.
        $parking_spaces = CarPark::all();

        // send response with the parking spaces details
        return response()->json([
            'status' => true,
            'count'  => $parking_spaces->count(),
            'result' => $parking_spaces
        ], 200);
    }

   /**
     * Get all parking spaces
     *
     * This method is recommended for api usage
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // get number of parking spaces to be fetched
        $per_page = $request->query('per_page') ?? 100;

        // return the requested number of parking spaces.
        $parking_spaces = CarPark::paginate($per_page);

        // send response with the parking spaces details
        return response()->json([
            'status' => true,
            'result' => $parking_spaces
        ], 200);
    }

    /**
     * Get the details for a car park
     *
     */
    public function show($id)
    {
        // Get the intended resource
        $car_park = CarPark::find($id);

        if (!$car_park) {
            return response()->json([
                'status'  => false,
                'message' => 'The Car Park cannot be found'
            ], 404);
        }
        else {
            // Output car park details
            return response()->json([
                'status'  => true,
                'result' => $car_park
            ], 200);
        }
    }

    /**
     * Get all activated car parks
     *
     */
    public function showActive()
    {
        // Get the intended resource
        $car_park = CarPark::whereStatus(1)->get();

        if (!is_null($car_park)) {
            // Output car park details
            return response()->json([
                'count'   => $car_park->count(),
                'status'  => true,
                'result'  => $car_park
            ], 200);
        }
        else {
            return response()->json([
                'status'  => false,
                'message' => 'There are no active car parks'
            ], 404);
        }
    }

    /**
     * Get all activated car parks assigned to an admin
     *
     */
    public function showAdminActive(CarPark $park)
    {
        // Get the intended resource
        $car_park = $this->user->parks()->whereStatus(1)->get();

        if ($car_park->isNotEmpty()) {
            // Output car park details
            return response()->json([
                'count'   => $car_park->count(),
                'status'  => true,
                'result'  => $car_park
            ], 200);
        }
        else {
            return response()->json([
                'status'  => false,
                'message' => 'There are no active car parks'
            ], 404);
        }
    }

    /**
     * Get all car parks created by a park admin
     *
     */
    public function theParksByAdmin(CarPark $park)
    {
        // Get the intended resource
        $car_park = $this->user->parks()->get();

        // Output car park details
        return response()->json([
            'count'   => $car_park->count(),
            'status'  => true,
            'result'  => $car_park
        ]);

    }

    /**
     * Get all in-active car parks
     *
     */
    public function showSuperInActive()
    {
        // Get the intended resource
        $car_park = CarPark::whereStatus(0)->get();

        if ($car_park->isNotEmpty()) {
            // Output car park details
            return response()->json([
                'count'   => $car_park->count(),
                'status'  => true,
                'result'  => $car_park
            ], 200);
        }
        else {
            return response()->json([
                'status'  => false,
                'message' => 'There are no in-active car parks'
            ], 404);
        }
    }

    /**
     * Get all deactivated admin's car parks
     *
     */
    public function showInActive(CarPark $park)
    {
        // Get the intended resource
        $car_park = $this->user->parks()->whereStatus(0)->get();

        if ($car_park->isNotEmpty()) {
            // Output car park details
            return response()->json([
                'count'   => $car_park->count(),
                'status'  => true,
                'result'  => $car_park
            ], 200);
        }
        else {
            return response()->json([
                'status'  => false,
                'message' => 'There are no in-active car parks'
            ], 404);
        }
    }

    /**
     * Activate a car park
     *
     */
    public function activate($park_id, CarPark $park)
    {
        // Get the intended resource
        $car_park = $this->user->parks()->find($park_id);

        if (!is_null($car_park)) {
            // Get the status of the park
            $status = $car_park->status;

            if($status == 1) {
                return response()->json([
                    'status'  => false,
                    'message' => 'The car park is currently active'
                ], 409);
            }
            else {
                $car_park->status = 1;

                if ($car_park->save()) {
                    return response()->json([
                        'status'  => true,
                        'message' => 'The car park is now activated'
                    ], 200);
                }
            }
        }
        else {
            return response()->json([
                'status'  => false,
                'message' => 'You are not assigned to this car park as an admin user'
            ], 403);
        }
    }

    /**
     * Deactivate a car park
     *
     */
    public function deactivate($park_id, CarPark $park)
    {
        // Get the intended resource
        $car_park = $this->user->parks()->find($park_id);

        if (!is_null($car_park)) {
            // Get the status of the park
            $status = $car_park->status;

            if ($status == 0) {
                return response()->json([
                    'status'  => false,
                    'message' => 'The car park is already deactivated'
                ], 409);
            }
            else {
                $car_park->status = 0;

                if ($car_park->save()) {
                    return response()->json([
                        'status'  => true,
                        'message' => 'The car park has been deactivated'
                    ], 200);
                }
            }
        }
        else {
            return response()->json([
                'status'  => false,
                'message' => 'You are not assigned to this car park as an admin user'
            ], 403);
        }
    }

    /**
     * Delete a car park
     *
     */
    public function delete($id, CarPark $park)
    {
        // Get the intended resource
        $car_park = $this->user->parks()->find($id);

        if (!is_null($car_park)) {
            if ($car_park->delete()) {
                return response()->json([
                    'status'  => true,
                    'message' => 'The car park has been deleted'
                ], 200);
            }
            else {
                return response()->json([
                    'status'  => false,
                    'message' => 'The car park could not be deleted at this moment'
                ], 501);
            }
        }
        else {
            return response()->json([
                'status'  => false,
                'message' => 'You are not assigned to this park'
            ], 404);
        }
    }

    private function upload($request, $image, $table = null)
    {
        $user = $this->user;

        $this->validate($request, [
            'image_name' => "image|max:5000|mimes:jpeg,jpg,png|between:1, 6000",
        ]);

        // Process image upload
        return $image->imageUpload($request, $table);
    }
}
