<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CalendarEventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CalendarEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CalendarEvent::forUser($request->user());

        // Filter by event type
        if ($request->has('event_type')) {
            $type = CalendarEventType::tryFrom($request->get('event_type'));
            if ($type) {
                $query->ofType($type);
            }
        }

        // Filter by date range
        if ($request->has('start') && $request->has('end')) {
            $query->scheduledBetween($request->get('start'), $request->get('end'));
        }

        $events = $query->orderBy('starts_at')->paginate($request->get('per_page', 20));

        return CalendarEventResource::collection($events);
    }

    public function calendar(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $events = CalendarEvent::forUser($request->user())
            ->scheduledBetween($request->get('start'), $request->get('end'))
            ->orderBy('starts_at')
            ->get();

        return CalendarEventResource::collection($events);
    }

    public function store(Request $request): CalendarEventResource
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'regex:/^#[A-Fa-f0-9]{6}$/'],
            'event_type' => ['nullable', 'string', Rule::in(CalendarEventType::values())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
        ]);

        $event = $request->user()->calendarEvents()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#3B82F6',
            'event_type' => $validated['event_type'] ?? 'meeting',
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'all_day' => $validated['all_day'] ?? false,
        ]);

        return new CalendarEventResource($event);
    }

    public function show(Request $request, CalendarEvent $event): CalendarEventResource
    {
        $this->authorizeEvent($request, $event);

        return new CalendarEventResource($event);
    }

    public function update(Request $request, CalendarEvent $event): CalendarEventResource
    {
        $this->authorizeEvent($request, $event);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'regex:/^#[A-Fa-f0-9]{6}$/'],
            'event_type' => ['nullable', 'string', Rule::in(CalendarEventType::values())],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
        ]);

        $event->update($validated);

        return new CalendarEventResource($event);
    }

    public function destroy(Request $request, CalendarEvent $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }

    public function reschedule(Request $request, CalendarEvent $event): CalendarEventResource
    {
        $this->authorizeEvent($request, $event);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $event->update([
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? $event->ends_at,
        ]);

        return new CalendarEventResource($event);
    }

    protected function authorizeEvent(Request $request, CalendarEvent $event): void
    {
        if ($event->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to event');
        }
    }
}
