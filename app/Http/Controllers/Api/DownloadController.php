<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DownloadTask;
use App\Jobs\DownloadYouTubeVideo;

class DownloadController extends Controller
{
    public function convert(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $task = DownloadTask::create([
            'url' => $request->url,
            'status' => 'pending'
        ]);

        // Start download background job
        DownloadYouTubeVideo::dispatch($task->id, $request->url);

        return response()->json([
            'message' => 'Procesamiento en cola',
            'task_id' => $task->id,
            'status' => 'pending'
        ], 202);
    }

    public function status($id)
    {
        $task = DownloadTask::findOrFail($id);
        
        return response()->json([
            'id' => $task->id,
            'status' => $task->status,
            'file_url' => $task->file_url,
            'error_message' => $task->error_message
        ]);
    }
}