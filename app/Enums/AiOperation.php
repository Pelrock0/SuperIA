<?php

namespace App\Enums;

enum AiOperation: string
{
    case Suggestion = 'suggestion';
    case Generation = 'generation';
    case Summary = 'summary';
    case Complement = 'complement';
    case Replenishment = 'replenishment';
    case CategoryInference = 'category_inference';
}
