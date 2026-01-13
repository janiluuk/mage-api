<?php

namespace App\JsonApi\Authorizers;

use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Auth\Authorizer;
use App\Constant\UserRoleConstant;

class ModelFileAuthorizer implements Authorizer
{
    /**
     * Check if the current user is an administrator.
     *
     * @param Request $request
     * @return bool
     */
    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        
        if (!$user || !$user->userRole) {
            return false;
        }

        $userRoleType = $user->userRole->getType();
        
        return $userRoleType === UserRoleConstant::ADMINISTRATOR ||
               $userRoleType === UserRoleConstant::SUPER_ADMINISTRATOR;
    }

    /**
     * Authorize the index controller action.
     * Anyone can list model files (public read access).
     *
     * @param Request $request
     * @param string $modelClass
     * @return bool
     */
    public function index(Request $request, string $modelClass): bool
    {
        return true;
    }

    /**
     * Authorize the store controller action.
     * Only administrators can create model files.
     *
     * @param Request $request
     * @param string $modelClass
     * @return bool
     */
    public function store(Request $request, string $modelClass): bool
    {
        return $this->isAdmin($request);
    }

    /**
     * Authorize the show controller action.
     * Anyone can view model file details (public read access).
     *
     * @param Request $request
     * @param object $model
     * @return bool
     */
    public function show(Request $request, object $model): bool
    {
        return true;
    }

    /**
     * Authorize the update controller action.
     * Only administrators can update model files.
     *
     * @param Request $request
     * @param object $model
     * @return bool
     */
    public function update(Request $request, object $model): bool
    {
        return $this->isAdmin($request);
    }

    /**
     * Authorize the destroy controller action.
     * Only administrators can delete model files.
     *
     * @param Request $request
     * @param object $model
     * @return bool
     */
    public function destroy(Request $request, object $model): bool
    {
        return $this->isAdmin($request);
    }

    /**
     * Authorize the show-related controller action.
     * Anyone can view related resources (public read access).
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool
     */
    public function showRelated(Request $request, object $model, string $fieldName): bool
    {
        return true;
    }

    /**
     * Authorize the show-relationship controller action.
     * Anyone can view relationships (public read access).
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool
     */
    public function showRelationship(Request $request, object $model, string $fieldName): bool
    {
        return true;
    }

    /**
     * Authorize the update-relationship controller action.
     * Only administrators can update relationships.
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool
     */
    public function updateRelationship(Request $request, object $model, string $fieldName): bool
    {
        return $this->isAdmin($request);
    }

    /**
     * Authorize the attach-relationship controller action.
     * Only administrators can attach relationships.
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool
     */
    public function attachRelationship(Request $request, object $model, string $fieldName): bool
    {
        return $this->isAdmin($request);
    }

    /**
     * Authorize the detach-relationship controller action.
     * Only administrators can detach relationships.
     *
     * @param Request $request
     * @param object $model
     * @param string $fieldName
     * @return bool
     */
    public function detachRelationship(Request $request, object $model, string $fieldName): bool
    {
        return $this->isAdmin($request);
    }
}
