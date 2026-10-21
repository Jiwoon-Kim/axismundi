/*
 * Copyright (C) 2024 The Android Open Source Project
 * SPDX-License-Identifier: Apache-2.0
 *
 * Adapted from MaterialShapes.java at MDC commit
 * 66b0eeb53afeb6025ffbbd37734b08e5bce197ed.
 * Changes: only the seven loading factories; JVM point transforms replace
 * Android Matrix/PointF/RectF. Rounding and Morph remain AndroidX library code.
 * The full license is in ../../../LICENSE-APACHE-2.0.txt.
 */
import androidx.collection.FloatFloatPair
import androidx.graphics.shapes.CornerRounding
import androidx.graphics.shapes.RoundedPolygon
import androidx.graphics.shapes.circle
import androidx.graphics.shapes.star
import kotlin.math.PI
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.hypot
import kotlin.math.min
import kotlin.math.sin

private data class Vertex(val x: Float, val y: Float, val rounding: CornerRounding = CornerRounding.Unrounded)

// MDC's radial template repetition, including its mirrored endpoint rule.
private fun custom(template: List<Vertex>, repeat: Int, mirrored: Boolean = false): RoundedPolygon {
    val radial = template.map { Vertex(atan2(it.y - .5f, it.x - .5f), hypot(it.x - .5f, it.y - .5f), it.rounding) }
    val span = (2 * PI / repeat).toFloat() / if (mirrored) 2 else 1
    val points = buildList {
        for (i in 0 until repeat * if (mirrored) 2 else 1) {
            val reverse = mirrored && i % 2 != 0
            for (j in radial.indices) {
                val index = if (reverse) radial.lastIndex - j else j
                if (index > 0 || !reverse) {
                    val p = radial[index]
                    val angle = span * i + if (reverse) span - p.x + 2 * radial[0].x else p.x
                    add(Vertex(p.y * cos(angle) + .5f, p.y * sin(angle) + .5f, p.rounding))
                }
            }
        }
    }
    return RoundedPolygon(points.flatMap { listOf(it.x, it.y) }.toFloatArray(),
        perVertexRounding = points.map { it.rounding }, centerX = .5f, centerY = .5f)
}

private fun rotate(shape: RoundedPolygon, degrees: Float): RoundedPolygon {
    val radians = degrees * PI / 180
    val c = cos(radians).toFloat()
    val s = sin(radians).toFloat()
    return shape.transformed { x, y -> FloatFloatPair(c * x - s * y, s * x + c * y) }
}

private fun normalize(shape: RoundedPolygon): RoundedPolygon {
    val b = shape.calculateMaxBounds()
    val scale = min(1f / (b[2] - b[0]), 1f / (b[3] - b[1]))
    val cx = (b[0] + b[2]) / 2
    val cy = (b[1] + b[3]) / 2
    return shape.transformed { x, y -> FloatFloatPair((x - cx) * scale + .5f, (y - cy) * scale + .5f) }
}

fun loadingPolygons(): List<RoundedPolygon> = listOf(
    custom(listOf(Vertex(.193f, .277f, CornerRounding(.053f)), Vertex(.176f, .055f, CornerRounding(.053f))), 10),
    rotate(RoundedPolygon.star(9, innerRadius = .8f, rounding = CornerRounding(.5f)), -90f),
    custom(listOf(Vertex(.500f, -.009f, CornerRounding(.172f))), 5),
    custom(listOf(Vertex(.961f, .039f, CornerRounding(.426f)), Vertex(1.001f, .428f),
        Vertex(1.000f, .609f, CornerRounding(1f))), 2, true),
    RoundedPolygon.star(8, innerRadius = .8f, rounding = CornerRounding(.15f)),
    custom(listOf(Vertex(1.237f, 1.236f, CornerRounding(.258f)), Vertex(.500f, .918f, CornerRounding(.233f))), 4),
    rotate(RoundedPolygon.circle().transformed { x, y -> FloatFloatPair(x, y * .64f) }, -45f)
).map(::normalize)
