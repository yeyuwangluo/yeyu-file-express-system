#!/usr/bin/env bash
set -euo pipefail

if [ ! -f artisan ]; then
  printf 'admin-file-actions smoke must run from Laravel project root\n' >&2
  exit 1
fi

php artisan tinker --execute='
Illuminate\Support\Facades\Queue::fake();
$controller = app(App\Http\Controllers\Web\AdminLiteController::class);
$file = App\Models\SharedFile::query()->where("status", "active")->firstOrFail();
$actions = ["extend", "rescan", "block", "delete"];
foreach ($actions as $action) {
    DB::beginTransaction();
    try {
        $path = "/admin-lite/files/".$file->id.($action === "delete" ? "" : "/".$action);
        $method = $action === "delete" ? "DELETE" : "POST";
        $request = Illuminate\Http\Request::create($path, $method, ["days" => 1, "confirm_text" => "CONFIRM"]);
        $request->attributes->set("admin_role", "owner");
        $controllerMethod = match ($action) {
            "extend" => "extendFile",
            "rescan" => "rescanFile",
            "block" => "blockFile",
            default => "deleteFile",
        };
        $controller->{$controllerMethod}($request, $file->fresh());
        echo $action.":ok".PHP_EOL;
    } catch (Throwable $e) {
        echo $action.":".get_class($e).":".$e->getMessage().PHP_EOL;
        exit(1);
    } finally {
        DB::rollBack();
    }
}
'
