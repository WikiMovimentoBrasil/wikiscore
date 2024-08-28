def get_username(strategy, details, user=None, *args, **kwargs):
    """
    This pipeline function customizes the behavior of python-social-auth to return the username 
    based on the project's custom user model.

    Parameters:
    - strategy: The strategy used by python-social-auth.
    - details: A dictionary containing user details retrieved from the authentication provider.
    - user: An optional User object. If provided, the function returns the username of this user.
    - *args: Additional positional arguments required by python-social-auth.
    - **kwargs: Additional keyword arguments required by python-social-auth.

    Returns:
    - dict: A dictionary containing the username. If a user is provided, it returns {'username': user.username}. 
            Otherwise, it returns {'username': details['username']}.
    """
    if user:
        return {"username": user.username}
    else:
        return {"username": details['username']}

def save_profile(backend, user, response, *args, **kwargs):
    """
    This pipeline function customizes the behavior of python-social-auth to save the username
    to the project's custom user model if the username has changed on the authentication provider.

    Parameters:
    - backend: The backend used by python-social-auth.
    - user: The User object to be saved.
    - response: The response from the authentication provider.
    - *args: Additional positional arguments required by python-social-auth.
    - **kwargs: Additional keyword arguments required by python-social-auth.

    Returns:
    - None
    """
    if backend.name == 'mediawiki':
        if user.username:
            if kwargs.get('details', False).get('username', False):
                if user.username != kwargs['details']['username']:
                    user.username = kwargs['details']['username']
                    user.save()