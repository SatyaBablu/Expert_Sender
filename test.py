def remove_duplicates(data):
    """Remove duplicates from a list while preserving order."""
    seen = []
    result = []
    for item in data:
        if item not in seen:
            seen.append(item)
            result.append(item)
    return result

function_list = [1, 2, 2, 3, 4, 4, 5]
print(remove_duplicates(function_list))  