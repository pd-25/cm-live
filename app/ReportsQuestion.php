<?php

namespace App;


use Illuminate\Database\Eloquent\Model;

class ReportsQuestion extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
     protected $table = 'kernel';
    protected $table = 'questions_for_report';
    protected $fillable = [
        'question_id', 'question_for', 'question', 'questionsId'
    ];

   /**
     * A post belongs to a user
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */

}
