use Illuminate\Auth\AuthenticationException;

protected function unauthenticated($request, AuthenticationException $exception)
{
    return response()->json([
        'message' => 'Non authentifié. Token manquant ou invalide.'
    ], 401);
}
