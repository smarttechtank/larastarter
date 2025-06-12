<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAvatarRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240', // 10MB max
    ];
  }

  /**
   * Get the error messages for the defined validation rules.
   *
   * @return array<string, string>
   */
  public function messages(): array
  {
    return [
      'avatar.required' => 'Avatar image is required.',
      'avatar.image' => 'Avatar must be an image file.',
      'avatar.mimes' => 'Avatar must be a file of type: jpeg, png, jpg, gif, svg, webp.',
      'avatar.max' => 'Avatar may not be greater than 10MB.',
    ];
  }
}
