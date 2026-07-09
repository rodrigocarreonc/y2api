<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DownloadYouTubeVideo;
use App\Models\DownloadTask;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function convert(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'format' => 'required|in:mp3,mp4',
        ]);

        $task = DownloadTask::create([
            'url' => $request->url,
            'format' => $request->format,
            'status' => 'pending',
        ]);

        // Start download background job
        DownloadYouTubeVideo::dispatch($task->id, $request->url);

        return response()->json([
            'message' => 'Procesamiento en cola',
            'task_id' => $task->id,
            'status' => 'pending',
        ], 202);
    }

    public function status($id)
    {
        $task = DownloadTask::findOrFail($id);

        $downloadUrl = $task->status === 'completed'
            ? url("/api/downloads/{$task->id}/file")
            : null;

        return response()->json([
            'id' => $task->id,
            'status' => $task->status,
            'file_url' => $downloadUrl,
            'title' => $task->title,
            'error_message' => $task->error_message,
        ]);
    }

    public function forceDownload($id)
    {
        $task = DownloadTask::findOrFail($id);

        if ($task->status !== 'completed' || ! $task->file_url) {
            abort(404, 'El archivo no está listo.');
        }

        $fileName = basename($task->file_url);
        $path = storage_path('app/public/downloads/'.$fileName);

        if (! file_exists($path)) {
            abort(404, 'El archivo ya fue eliminado del servidor.');
        }

        // Construimos el nombre de descarga usando el título que guardamos en la BD
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $downloadName = ($task->title ?? 'archivo_convertido').'.'.$extension;

        return response()->download($path, $downloadName);
    }
}
