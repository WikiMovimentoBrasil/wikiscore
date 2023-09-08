#!/bin/bash

# Get the current path
SCRIPTPATH="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"

# Check if the username contains "test"
if [[ "$SCRIPTPATH" != *"test"* ]]; then
  echo "Path does not contain 'test'. Performing git pull and exiting."
  git pull -r
  exit 0
fi

# Run git for-each-ref and store the output
git fetch -p
branches=$(git for-each-ref --format='%(committerdate:format:%s):%(refname)' refs/remotes/origin/)

# Initialize variables to keep track of the most recent branch and its commit date
most_recent_branch=""
most_recent_date="0"

# Loop through the branches
IFS=$'\n' # Set the input field separator to newline
for branch in $branches; do
  # Extract the commit date and branch name
  commit_date=$(echo "$branch" | cut -d: -f1)
  branch_name=$(echo "$branch" | cut -d: -f2 | sed 's/^refs\/remotes\/origin\///')

  # Check if the branch name is "HEAD" and skip it
  if [[ "$branch_name" == "HEAD" ]]; then
    continue
  fi

  # Check if the branch name contains "hotfix", "feature", or "develop"
  if [[ $branch_name == *hotfix* ]]; then
    priority=4
  elif [[ $branch_name == *release* ]]; then
    priority=3
  elif [[ $branch_name == *feature* ]]; then
    priority=2
  elif [[ $branch_name == *develop* ]]; then
    priority=1
  else
    priority=0
  fi

  # Compare commit dates and priorities
  if [[ "$commit_date" > "$most_recent_date" || ( "$commit_date" == "$most_recent_date" && $priority > $most_recent_priority ) ]]; then
    most_recent_date="$commit_date"
    most_recent_branch="$branch_name"
    most_recent_priority="$priority"
  fi
done

# Checkout the most recent branch
if [ -n "$most_recent_branch" ]; then
  git checkout "$most_recent_branch"
  git pull -r
  echo "Checked out branch: $most_recent_branch"
else
  echo "No remote branches found."
fi
