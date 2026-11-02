<?php
namespace Database\Factories;
use App\Models\ContactInquiry; use Illuminate\Database\Eloquent\Factories\Factory;
class ContactInquiryFactory extends Factory { protected $model=ContactInquiry::class; public function definition(): array{return ['name'=>fake()->name(),'email'=>fake()->safeEmail(),'phone'=>fake()->optional()->phoneNumber(),'subject'=>fake()->optional()->sentence(4),'message'=>fake()->paragraph(),'status'=>'new','handled_at'=>null];} }
