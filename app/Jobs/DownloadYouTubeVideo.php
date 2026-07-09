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

        $outputDirectory = storage_path('app/public/downloads');
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }
        
        // Usamos una plantilla con el ID de la tarea para evitar colisiones y %(title)s para el título.
        // También sanitizamos el nombre usando la sintaxis de yt-dlp (reemplazando espacios y caracteres raros).
        $pathTemplate = $outputDirectory . '/task_' . $this->taskId . '_%(title)s.%(ext)s';

        if ($task->format === 'mp3') {
            $command = [
                'yt-dlp',
                '-x', 
                '--audio-format', 'mp3', 
                '--audio-quality', '0', 
                '--print', 'after_move:filepath', // Imprime la ruta final
                '-o', $pathTemplate,
                $this->videoUrl
            ];
        } else {
            $command = [
                'yt-dlp',
                '-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
                '--merge-output-format', 'mp4',
                '--print', 'after_move:filepath', // Imprime la ruta final
                '-o', $pathTemplate,
                $this->videoUrl
            ];
        }

        $process = new Process($command);
        $process->setTimeout(600); 

        try {
            $process->mustRun();
            
            // yt-dlp imprimirá la ruta completa en la salida estándar.
            // Limpiamos la salida (trim) para quitar saltos de línea.
            $finalPath = trim($process->getOutput());
            
            // Obtenemos solo el nombre del archivo de esa ruta
            $fileName = basename($finalPath);

            $task->update([
                'status' => 'completed',
                'file_url' => asset('storage/downloads/' . $fileName),
                // Extraemos un título "limpio" quitando el prefijo "task_ID_" y la extensión
                'title' => pathinfo(preg_replace('/^task_\d+_/', '', $fileName), PATHINFO_FILENAME)
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