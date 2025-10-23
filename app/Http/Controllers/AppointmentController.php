<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Exception;

class AppointmentController extends Controller
{

    private function sendSMS($phoneNumber, $message)
    {
        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.auth_token');
            $from = config('services.twilio.phone_number');

            $twilio = new Client($sid, $token);

            $message = $twilio->messages->create(
                $phoneNumber,
                [
                    'from' => $from,
                    'body' => $message
                ]
            );

            Log::info('SMS sent successfully', [
                'to' => $phoneNumber,
                'message_sid' => $message->sid
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send SMS', [
                'to' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    /**
     * @OA\Post(
     *     path="/book-appointment",
     *     summary="Book a new appointment",
     *     tags={"Appointments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name", "phone", "unit_service", "datetime", "type"},
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="dob", type="string", format="date", example="1990-01-01"),
     *                 @OA\Property(property="unit_service", type="string", example="General Checkup"),
     *                 @OA\Property(property="datetime", type="string", format="date-time", example="2023-12-01 10:00:00"),
     *                 @OA\Property(property="type", type="string", enum={"in_person", "online", "follow_up"}, example="in_person"),
     *                 @OA\Property(property="notes", type="string", example="Additional notes about the appointment")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Appointment booked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Appointment booked successfully"),
     *             @OA\Property(property="confirmation_code", type="string", example="RHC-20231201-ABC123"),
     *             @OA\Property(property="appointment_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", example={"name": {"The name field is required."}})
     *         )
     *     )
     * )
     */
    public function bookAppointment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'dob' => 'nullable|date',
            'unit_service' => 'required|string|max:255',
            'datetime' => 'required|date',
            'type' => 'required|in:in_person,online,follow_up',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find or create patient
        $patient = Patient::firstOrCreate(
            ['phone' => $request->phone],
            [
                'name' => $request->name,
                'email' => $request->email,
                'dob' => $request->dob
            ]
        );

        // Generate confirmation code
        $confirmationCode = 'RHC-' . date('Ymd') . '-' . Str::upper(Str::random(6));

        // Create appointment
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'unit_service' => $request->unit_service,
            'datetime' => $request->datetime,
            'type' => $request->type,
            'notes' => $request->notes,
            'confirmation_code' => $confirmationCode,
            'status' => 'pending'
        ]);

        // Format the appointment date for better readability
        $appointmentDate = date('F j, Y \a\t g:i A', strtotime($request->datetime));

        // Create confirmation message
        $message = "Hello {$request->name}, thank you for booking with Reliance Health Clinic! " .
            "We have received your appointment request for {$request->unit_service} " .
            "scheduled on {$appointmentDate}. " .
            "Your confirmation code is: {$confirmationCode}. " .
            "We will contact you shortly to confirm your appointment.";

        // Send confirmation SMS
        $smsSent = $this->sendSMS($request->phone, $message);

        // Log SMS status
        if ($smsSent) {
            Log::info('Appointment confirmation SMS sent successfully', [
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'phone' => $request->phone
            ]);
        } else {
            Log::warning('Failed to send appointment confirmation SMS', [
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'phone' => $request->phone
            ]);
        }

        return response()->json([
            'message' => 'Appointment booked successfully',
            'confirmation_code' => $confirmationCode,
            'appointment_id' => $appointment->id,
            'sms_sent' => $smsSent
        ], 201);
    }
    /**
     * @OA\Get(
     *     path="/appointments",
     *     summary="Get all appointments (admin/staff only)",
     *     tags={"Appointments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"pending", "confirmed", "cancelled", "completed"})
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=false,
     *         description="Filter by date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="unit_service",
     *         in="query",
     *         required=false,
     *         description="Filter by unit service",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Search by confirmation code or patient details",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Appointments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="patient_id", type="integer", example=1),
     *                     @OA\Property(property="unit_service", type="string", example="General Checkup"),
     *                     @OA\Property(property="datetime", type="string", format="date-time"),
     *                     @OA\Property(property="type", type="string", example="in_person"),
     *                     @OA\Property(property="notes", type="string", example="Additional notes"),
     *                     @OA\Property(property="confirmation_code", type="string", example="RHC-20231201-ABC123"),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="patient",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="phone", type="string", example="+1234567890"),
     *                         @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                         @OA\Property(property="dob", type="string", format="date", example="1990-01-01")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="first_page_url", type="string", example="http://example.com/appointments?page=1"),
     *             @OA\Property(property="from", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=1),
     *             @OA\Property(property="last_page_url", type="string", example="http://example.com/appointments?page=1"),
     *             @OA\Property(
     *                 property="links",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="url", type="string", nullable=true, example="http://example.com/appointments?page=1"),
     *                     @OA\Property(property="label", type="string", example="1"),
     *                     @OA\Property(property="active", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(property="next_page_url", type="string", nullable=true, example=null),
     *             @OA\Property(property="path", type="string", example="http://example.com/appointments"),
     *             @OA\Property(property="per_page", type="integer", example=20),
     *             @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
     *             @OA\Property(property="to", type="integer", example=10),
     *             @OA\Property(property="total", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function getAppointments(Request $request)
    {
        // Only accessible to admin/staff
        if (!in_array($request->user()->role, ['admin', 'staff'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Appointment::with('patient');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('datetime', $request->date);
        }

        if ($request->has('unit_service')) {
            $query->where('unit_service', 'like', '%' . $request->unit_service . '%');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('confirmation_code', 'like', "%$search%")
                    ->orWhereHas('patient', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%");
                    });
            });
        }

        $appointments = $query->orderBy('datetime', 'desc')->paginate(20);

        return response()->json($appointments);
    }

    /**
     * @OA\Put(
     *     path="/appointments/{id}",
     *     summary="Update an appointment (admin only)",
     *     tags={"Appointments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Appointment ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="status", type="string", enum={"pending", "confirmed", "cancelled", "completed"}),
     *                 @OA\Property(property="datetime", type="string", format="date-time", example="2023-12-01 10:00:00"),
     *                 @OA\Property(property="notes", type="string", example="Updated notes")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Appointment updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Appointment updated successfully"),
     *             @OA\Property(
     *                 property="appointment",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="patient_id", type="integer", example=1),
     *                 @OA\Property(property="unit_service", type="string", example="General Checkup"),
     *                 @OA\Property(property="datetime", type="string", format="date-time"),
     *                 @OA\Property(property="type", type="string", example="in_person"),
     *                 @OA\Property(property="notes", type="string", example="Updated notes"),
     *                 @OA\Property(property="confirmation_code", type="string", example="RHC-20231201-ABC123"),
     *                 @OA\Property(property="status", type="string", example="confirmed"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="patient",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="phone", type="string", example="+1234567890"),
     *                     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                     @OA\Property(property="dob", type="string", format="date", example="1990-01-01")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Appointment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Appointment not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", example={"status": {"The selected status is invalid."}})
     *         )
     *     )
     * )
     */
    public function updateAppointment(Request $request, $id)
    {
        // Only accessible to admin
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $appointment = Appointment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,confirmed,cancelled,completed',
            'datetime' => 'sometimes|date',
            'notes' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $appointment->update($request->only(['status', 'datetime', 'notes']));

        return response()->json([
            'message' => 'Appointment updated successfully',
            'appointment' => $appointment->load('patient')
        ]);
    }
}