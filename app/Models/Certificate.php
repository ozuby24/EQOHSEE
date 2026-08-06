<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Certificate extends Model {
    protected $fillable = ['user_id','course_id','certificate_number','recipient_name','course_title','final_score','signed_by_name','signatory_id','issued_at','verification_code','company_id','template'];
    protected function casts(): array { return ['issued_at' => 'datetime']; }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function course(): BelongsTo    { return $this->belongsTo(Course::class); }
    public function signatory(): BelongsTo { return $this->belongsTo(Signatory::class); }
    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }

    /** Teks yang dikodekan pada barcode. */
    public function barcodeText(): string
    {
        return $this->verification_code ?: $this->certificate_number ?: ('EQ'.$this->id);
    }
}
