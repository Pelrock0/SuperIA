<?php

namespace App\Enums;

enum ProductCategory: string
{
    case FrutasVerduras = 'frutas_verduras';
    case CarnesPescados = 'carnes_pescados';
    case LacteosHuevos = 'lacteos_huevos';
    case Panaderia = 'panaderia';
    case Bebidas = 'bebidas';
    case Congelados = 'congelados';
    case Limpieza = 'limpieza';
    case HigienePersonal = 'higiene_personal';
    case Conservas = 'conservas';
    case Otros = 'otros';
}
