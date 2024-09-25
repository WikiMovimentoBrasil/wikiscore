from django.db import transaction
from .models import Profile
import logging

logger = logging.getLogger(__name__)

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
        details = kwargs.get('details', {})

        try:
            new_username = details.get('username')
            global_id = details.get('userID')

            if not global_id:
                logger.error("No global_id provided in the MediaWiki response.")
                return

            if not new_username:
                logger.warning(f"Username is missing for global_id {global_id}.")
                return

            with transaction.atomic():
                if user.username != new_username:
                    user.username = new_username
                    user.save()

                profile, created = Profile.objects.get_or_create(global_id=global_id)
                if profile.account != user:
                    profile.account = user
                    profile.save()
                if profile.username != new_username:
                    profile.username = new_username
                    profile.save()

        except Exception as e:
            logger.error(f"Error while saving profile for user {user.id}: {str(e)}")
