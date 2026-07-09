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

        // 1. Usar el formato de la BD para la extensión
        $fileName = uniqid('media_') . '.' . $task->format; 
        $outputDirectory = storage_path('app/public/downloads');
        
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }
        
        $absolutePath = $outputDirectory . '/' . $fileName;

        // 2. Definir los comandos según el formato
        if ($task->format === 'mp3') {
            // Comandos para extraer audio con FFmpeg
            $command = [
                'yt-dlp',
                '-x', // Extraer audio
                '--audio-format', 'mp3', // Formato de destino
                '--audio-quality', '0', // 0 = mejor calidad posible (VBR)
                '-o', $absolutePath,
                $this->videoUrl
            ];
        } else {
            // Comandos originales para video
            $command = [
                'yt-dlp',
                '-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
                '--merge-output-format', 'mp4',
                '-o', $absolutePath,
                $this->videoUrl
            ];
        }

        $process = new Process($command);
        $process->setTimeout(600); 

        try {
            // Ejecutar el comando en la terminal
            $process->mustRun();

            // Si llegamos aquí, la descarga terminó correctamente
            $task->update([
                'status' => 'completed',
                'file_url' => asset('storage/downloads/' . $fileName) 
            ]);

        } catch (\Exception $exception) {
            \Illuminate\Support\Facades\Log::error("Error yt-dlp [Task {$this->taskId}]: " . $exception->getMessage());
            
            $task->update([
                'status' => 'failed',
                'error_message' => 'No se pudo procesar el enlace. Verifica que sea público.'
            ]);
        }
    }
}