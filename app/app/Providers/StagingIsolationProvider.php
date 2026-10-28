<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSending;
class StagingIsolationProvider extends ServiceProvider {
 public function boot(): void {
  if (!app()->environment('staging')) return;
  Http::globalRequestMiddleware(function ($request) {
   if (!in_array($request->getMethod(), ['GET','HEAD','OPTIONS'])) throw new \RuntimeException('External writes disabled in staging');
   return $request;
  });
  Event::listen(MessageSending::class, fn () => false);
  $this->app->afterResolving('filesystem', function ($manager) {
   $client=$manager->disk('minio-media')->getClient();
   $client->getHandlerList()->appendInit(\Aws\Middleware::mapCommand(function ($command) {
    if (!in_array($command->getName(), ['GetObject','HeadObject','ListObjects','ListObjectsV2','HeadBucket','GetBucketLocation'])) throw new \RuntimeException('S3 writes disabled in staging');
    return $command;
   }), 'staging-read-only');
  });
 }
}
