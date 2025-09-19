<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketReplyStoreRequest;
use App\Http\Requests\TicketStoreRequest;
use App\Http\Resources\TicketReplyResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketReply;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{

    public function index(Request $request)
    {
        try {
            $query = Ticket::query();

            $query->orderBy('created_at', 'desc');

            if ($request->search) {
                $query->where('code', 'like', '%' . $request->search . '%')
                    ->orWhere('title', 'like', '%' . $request->search . '%');
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->priority) {
                $query->where('priority', $request->priority);
            }

            if (auth()->user()->role == 'user') {
                $query->where('user_id', auth()->user()->id);
            }

            $ticket = $query->paginate(10);

            return response()->json([
                'message' => 'List Ticket',
                'data' => TicketResource::collection($ticket)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Tidak ada data ticket',
                'data' => null
            ], 404);
        }
    }

    public function show($code)
    {
        try {
            $ticket = Ticket::where('code', $code)->first();

            if (!$ticket) {
                return response()->json([
                    'message' => 'Ticket tidak ditemukan',
                    'data' => null
                ], 404);
            }

            if (auth()->user()->role == 'user' && $ticket->user_id != auth()->user()->id) {
                return response()->json([
                'message' => 'Ticket tidak ditemukan',
                'data' => null
                ], 403);
            }

            return response()->json([
                'message' => 'Detail Ticket',
                'data' => new TicketResource($ticket)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi Kesalahan',
                'data' => null
            ], 500);
        }
    }
    public function store(TicketStoreRequest $request)
    {
        $data = $request->validated();

        DB::beginTransaction();

        try {

            if (auth()->user()->role !== 'user') {
                return response()->json([
                    'message' => 'Hanya masyarakat yang dapat membuat ticket',
                    'data' => null
                ], 403);
            }

            $ticket = new Ticket;
            $ticket->user_id = auth()->user()->id;
            $ticket->code = 'TIC-' . rand(10000, 99999);
            $ticket->title = $data['title'];
            $ticket->description = $data['description'];
            $ticket->priority = $data['priority'];
            $ticket->save();

            DB::commit();

            return response()->json([
                'message' => 'Ticket berhasil ditambahkan',
                'data' => new TicketResource($ticket)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi Kesalahan',
                'data' => null
            ], 500);
        }
    }

    public function storeReply(TicketReplyStoreRequest $request, $code)
    {
        $data = $request->validated();

        DB::beginTransaction();

        try {
            $ticket = Ticket::where('code', $code)->first();

            if (!$ticket) {
                return response()->json([
                    'message' => 'Ticket tidak ditemukan',
                    'data' => null
                ], 404);
            }

            if (auth()->user()->role == 'user' && $ticket->user_id != auth()->user()->id) {
                return response()->json([
                    'message' => 'Anda tidak memiliki izin untuk membuat reply pada ticket ini',
                ], 403);
            }

            $ticketReply = new TicketReply;
            $ticketReply->ticket_id = $ticket->id;
            $ticketReply->user_id = auth()->user()->id;
            $ticketReply->content = $data['content'];
            $ticketReply->save();

            if (auth()->user()->role == 'admin') {
                $ticket->status = $data['status'];
                if ($data['status'] == 'resolved') {
                    $ticket->completed_at = now();
                }
                $ticket->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Reply berhasil ditambahkan',
                'data' => new TicketReplyResource($ticketReply)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi Kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
