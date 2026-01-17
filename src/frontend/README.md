# SPARXSTAR 3IAtlas Dictionary Form

A frontend form for adding and editing dictionary entries in WordPress with role-based access control.

## Features

- ✅ **Password Protected**: Only logged-in users can access
- ✅ **Role-Based Permissions**:
    - Contributors: Can add new entries
    - Editors: Can add AND edit existing entries
- ✅ **Non-Destructive Editing**: Editing creates a new draft instead of modifying the original
- ✅ **All submissions saved as drafts** for review before publishing
- ✅ **Media Upload**: Support for images and audio files
- ✅ **Example Sentences**: Repeatable fields with translations
- ✅ **Synonym Search**: Live search and selection of related words
- ✅ **AJAX Submission**: No page reload, instant feedback
- ✅ **Responsive Design**: Works on desktop, tablet, and mobile

## Installation

### Option 1: As a Plugin

1. Create a new folder in `/wp-content/plugins/` called `SPARXSTAR 3IAtlas-dictionary-form`
2. Copy all three files into this folder:
    - `3IAtlasDictionaryForm.php`
    - `3iatlas-dictionary-form.js`
    - `3iatlas-diictionary-form.css`
3. Go to WordPress admin → Plugins
4. Activate "SPARXSTAR 3IAtlas Dictionary Form"

### Option 2: In Theme

1. Copy the PHP code from `SPARXSTAR 3IAtlas-dictionary-form.php` into your theme's `functions.php`
2. Upload the CSS and JS files to your theme folder
3. Update the file paths in the `SPARXSTAR 3IAtlas_dict_form_enqueue_scripts()` function to match your theme structure

## Usage

### Basic Form (Add New Entry)

Add this shortcode to any page:

```
[SPARXSTAR 3IAtlas_dictionary_form]
```

### Edit Existing Entry

To pre-populate the form with an existing entry for editing:

```
[SPARXSTAR 3IAtlas_dictionary_form entry_id="123"]
```

Replace `123` with the actual post ID of the dictionary entry.

## User Permissions

### Required WordPress Roles

- **Contributor or higher**: Can add new entries
- **Editor or higher**: Can edit existing entries

### Setting User Roles

1. Go to WordPress admin → Users
2. Edit a user
3. Set their role to "Contributor" (for adding only) or "Editor" (for adding and editing)

## Form Fields

### Basic Information

- Word/Term (required)
- Translation
- Translation (English)
- Translation (French)
- Part of Speech
- Search String (English)
- Search String (French)
- IPA Pronunciation
- Rating Average (1-5)

### Media

- Audio Recording (mp3, wav)
- Word Photo (image)

### Content

- Word Origin
- Extract (Long Definition)

### Example Sentences

- Sentence
- Translation
- Translation (English)
- Translation (French)

### Relationships

- Synonyms (searchable relationship field)

## Behavior

### Adding New Entries

- All fields are blank
- Submit button says "Add Entry (Draft)"
- Creates a new post with status "draft"
- Form resets after successful submission

### Editing Entries

- Form pre-populated with existing data
- Submit button says "Save as New Draft"
- Creates a NEW draft post (does not modify original)
- Original entry ID stored in `_SPARXSTAR 3IAtlas_edited_from` meta field
- Can track edit history

### Security

- Nonce verification on all AJAX requests
- User authentication required
- Role-based permission checks
- Sanitized input on all fields

## Customization

### Styling

The form uses CSS variables for easy customization. Edit these in `SPARXSTAR 3IAtlas-dictionary-form.css`:

```css
:root {
    --color-primary: #1a472a;
    --color-accent: #c17817;
    --color-error: #b91c1c;
    --color-success: #15803d;
    /* ... etc */
}
```

### Adding Custom Fields

1. Add the field to the HTML form in the PHP file
2. Add the field name to the `$meta_fields` array in the AJAX handler
3. Style the field in the CSS file if needed

## Troubleshooting

### "You must be logged in to access this form"

- Make sure you're logged into WordPress
- Check that the user has Contributor or Editor role

### "Only editors can edit entries"

- User must have Editor role or higher to edit existing entries
- Contributors can only add new entries

### Media uploader not working

- Make sure WordPress media library is properly configured
- Check browser console for JavaScript errors
- Verify `wp_enqueue_media()` is being called

### AJAX submission fails

- Check that the plugin/theme is properly activated
- Verify nonce is being generated correctly
- Check PHP error logs for server-side issues

## Support

For issues or questions, check:

- WordPress user role settings
- PHP error logs
- Browser console for JavaScript errors
- Network tab for failed AJAX requests

## License

This code is provided as-is for use with the SPARXSTAR 3IAtlas Dictionary project.
