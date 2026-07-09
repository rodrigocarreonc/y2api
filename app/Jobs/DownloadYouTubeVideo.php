<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\DownloadTask;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DownloadYouTubeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $taskId, 
        public string $videoUrl
    ) {}

    public function handle(): void
    {
        $task = DownloadTask::find($this->taskId);
        
        if (!$task || $task->status !== 'pending') {
            return; 
        }

        $task->update(['status' => 'processing']);

        // Crear nombre único y definir la carpeta de destino pública
        $fileName = uniqid('video_') . '.mp4'; 
        $outputDirectory = storage_path('app/public/downloads');
        
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }
        
        $absolutePath = $outputDirectory . '/' . $fileName;

        // Configurar el comando yt-dlp para descargar el mejor MP4
        $process = new Process([
            'yt-dlp',
            '-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
            '--merge-output-format', 'mp4',
            '-o', $absolutePath,
            $this->videoUrl
        ]);

        // 10 minutos de tiempo máximo de ejecución para el comando
        $process->setTimeout(600); 

        try {
            // Ejecutar el comando en la terminal
            $process->mustRun();

            // Si llegamos aquí, la descarga terminó correctamente
            $task->update([
                'status' => 'completed',
                'file_url' => asset('storage/downloads/' . $fileName) 
            ]);

        } catch (ProcessFailedException $exception) {
            // Registrar el error real en los logs de Laravel por si necesitas depurar
            Log::error("Error en yt-dlp [Task {$this->taskId}]: " . $exception->getMessage());
            
            $task->update([
                'status' => 'failed',
                'error_message' => 'No se pudo descargar el video. Es posible que el enlace no sea válido, sea privado o esté restringido.'
            ]);
        }
    }
}