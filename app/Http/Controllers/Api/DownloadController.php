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
        
        $downloadUrl = $task->status === 'completed' 
            ? url("/api/downloads/{$task->id}/file") 
            : null;

        return response()->json([
            'id' => $task->id,
            'status' => $task->status,
            'file_url' => $downloadUrl, // Entregamos la URL que fuerza la descarga
            'error_message' => $task->error_message
        ]);
    }

    public function forceDownload($id){
        $task = DownloadTask::findOrFail($id);

        if ($task->status !== 'completed' || !$task->file_url) {
            abort(404, 'El archivo no está listo.');
        }

        // Extraemos solo "video_xxxxxx.mp4" de la URL guardada
        $fileName = basename($task->file_url);
        $path = storage_path('app/public/downloads/' . $fileName);

        if (!file_exists($path)) {
            abort(404, 'El archivo ya fue eliminado del servidor.');
        }

        // El segundo parámetro es el nombre con el que se descargará el archivo
        return response()->download($path, 'video_convertido.mp4');
    }
}