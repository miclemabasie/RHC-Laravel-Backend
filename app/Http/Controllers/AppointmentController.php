<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
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

        // Send confirmation (implement your email/SMS service)
        // $this->sendConfirmation($patient, $appointment);

        return response()->json([
            'message' => 'Appointment booked successfully',
            'confirmation_code' => $confirmationCode,
            'appointment_id' => $appointment->id
        ], 201);
    }

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